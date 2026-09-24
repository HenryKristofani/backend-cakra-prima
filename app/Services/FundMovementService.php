<?php

namespace App\Services;

use App\Models\FundMovement;
use App\Models\FundSource;
use App\Models\Project;
use App\Models\ProjectKasTransaction;
use App\Models\Transaction;
use App\Enums\FundMovementType;
use Illuminate\Support\Facades\DB;

class FundMovementService
{
    /**
     * Generate reference number securely with lock.
     * Must be called inside a DB::transaction.
     */
    private function generateReferenceNumber(): string
    {
        $year = now()->year;
        
        $lastNumberStr = FundMovement::whereYear('created_at', $year)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('reference_number');

        $sequence = 1;
        if ($lastNumberStr) {
            // Expected format: FND-2026-0001
            $parts = explode('-', $lastNumberStr);
            if (count($parts) === 3) {
                $sequence = (int) $parts[2] + 1;
            }
        }

        return 'FND-' . $year . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function createInitialAllocation(array $data, int $userId): FundMovement
    {
        return DB::transaction(function () use ($data, $userId) {
            // Lock the FundSource row to prevent concurrent over-allocation.
            // Two simultaneous allocations from the same fund source would both
            // read the same total_allocated and both pass the check — locking
            // the row serialises them so only one runs the check at a time.
            $fundSource = FundSource::where('id', $data['fund_source_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $project = Project::findOrFail($data['destination_project_id']);

            // Guard: total_allocated (InitialAllocation only) + new amount must not exceed initial_amount.
            $totalAllocated = (float) FundMovement::where('fund_source_id', $fundSource->id)
                ->where('type', FundMovementType::InitialAllocation->value)
                ->sum('amount');

            $requested  = (float) $data['amount'];
            $available  = (float) $fundSource->initial_amount - $totalAllocated;

            if ($requested > $available) {
                $fmt = fn(float $v) => 'Rp ' . number_format($v, 0, ',', '.');
                throw new \Exception(
                    "Alokasi melebihi batas fund source. " .
                    "Sisa tersedia: {$fmt($available)}, diminta: {$fmt($requested)}."
                );
            }
            
            $referenceNumber = $this->generateReferenceNumber();

            // 1. Insert fund_movements
            $movement = FundMovement::create([
                'fund_source_id' => $fundSource->id,
                'type' => FundMovementType::InitialAllocation,
                'source_project_id' => null,
                'destination_project_id' => $project->id,
                'amount' => $data['amount'],
                'reference_number' => $referenceNumber,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            // 2. Insert ke kas
            $description = "Alokasi Modal: {$fundSource->name} (Ref: {$referenceNumber})";
            $date = $data['date'] ?? now()->format('Y-m-d');
            $paymentMethod = $data['payment_method'];

            if ($project->is_isolated_cash) {
                ProjectKasTransaction::create([
                    'project_id'       => $project->id,
                    'fund_movement_id' => $movement->id,
                    'user_id'          => $userId,
                    'date'             => $date,
                    'description'      => $description,
                    'payment_method'   => $paymentMethod,
                    'income'           => $data['amount'],
                    'expense'          => 0,
                ]);
            } else {
                Transaction::create([
                    'project_id'       => $project->id,
                    'fund_movement_id' => $movement->id,
                    'account_id'       => $data['account_id'],
                    'user_id'          => $userId,
                    'date'             => $date,
                    'description'      => $description,
                    'payment_method'   => $paymentMethod,
                    'income'           => $data['amount'],
                    'expense'          => 0,
                ]);
            }

            return $movement;
        });
    }

    public function createTransfer(array $data, int $userId): FundMovement
    {
        return DB::transaction(function () use ($data, $userId) {
            $fundSource = FundSource::findOrFail($data['fund_source_id']);
            
            // 1. Lock projects to prevent deadlocks and race conditions
            $projectIds = [$data['source_project_id'], $data['destination_project_id']];
            sort($projectIds);
            
            $projects = Project::whereIn('id', $projectIds)->lockForUpdate()->get()->keyBy('id');
            $sourceProject = $projects[$data['source_project_id']];
            $destinationProject = $projects[$data['destination_project_id']];
            
            // 2. Calculate source project balance
            $income = 0;
            $expense = 0;
            
            if ($sourceProject->is_isolated_cash) {
                $income = ProjectKasTransaction::where('project_id', $sourceProject->id)->sum('income');
                $expense = ProjectKasTransaction::where('project_id', $sourceProject->id)->sum('expense');
            } else {
                $income = Transaction::where('project_id', $sourceProject->id)->sum('income');
                $expense = Transaction::where('project_id', $sourceProject->id)->sum('expense');
            }
            
            $currentBalance = (float) $income - (float) $expense;
            $amount = (float) $data['amount'];
            
            if ($currentBalance < $amount) {
                throw new \Exception("Saldo project asal tidak mencukupi. Saldo tersedia: {$currentBalance}, diminta: {$amount}");
            }
            
            // 3. Generate Reference
            $referenceNumber = $this->generateReferenceNumber();
            
            // 4. Insert Movement
            $movement = FundMovement::create([
                'fund_source_id' => $fundSource->id,
                'type' => FundMovementType::Transfer,
                'source_project_id' => $sourceProject->id,
                'destination_project_id' => $destinationProject->id,
                'amount' => $amount,
                'reference_number' => $referenceNumber,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);
            
            $date = $data['date'] ?? now()->format('Y-m-d');
            $paymentMethod = $data['payment_method'];
            
            // 5. Insert Expense for Source Project
            $sourceDescription = "Realokasi Modal ke {$destinationProject->name}: {$fundSource->name} (Ref: {$referenceNumber})";
            if ($sourceProject->is_isolated_cash) {
                ProjectKasTransaction::create([
                    'project_id'       => $sourceProject->id,
                    'fund_movement_id' => $movement->id,
                    'user_id'          => $userId,
                    'date'             => $date,
                    'description'      => $sourceDescription,
                    'payment_method'   => $paymentMethod,
                    'income'           => 0,
                    'expense'          => $amount,
                ]);
            } else {
                Transaction::create([
                    'project_id'       => $sourceProject->id,
                    'fund_movement_id' => $movement->id,
                    'account_id'       => $data['source_account_id'],
                    'user_id'          => $userId,
                    'date'             => $date,
                    'description'      => $sourceDescription,
                    'payment_method'   => $paymentMethod,
                    'income'           => 0,
                    'expense'          => $amount,
                ]);
            }
            
            // 6. Insert Income for Destination Project
            $destinationDescription = "Realokasi Modal dari {$sourceProject->name}: {$fundSource->name} (Ref: {$referenceNumber})";
            if ($destinationProject->is_isolated_cash) {
                ProjectKasTransaction::create([
                    'project_id'       => $destinationProject->id,
                    'fund_movement_id' => $movement->id,
                    'user_id'          => $userId,
                    'date'             => $date,
                    'description'      => $destinationDescription,
                    'payment_method'   => $paymentMethod,
                    'income'           => $amount,
                    'expense'          => 0,
                ]);
            } else {
                Transaction::create([
                    'project_id'       => $destinationProject->id,
                    'fund_movement_id' => $movement->id,
                    'account_id'       => $data['destination_account_id'],
                    'user_id'          => $userId,
                    'date'             => $date,
                    'description'      => $destinationDescription,
                    'payment_method'   => $paymentMethod,
                    'income'           => $amount,
                    'expense'          => 0,
                ]);
            }
            
            return $movement;
        });
    }

    public function createWithdrawal(array $data, int $userId): FundMovement
    {
        return DB::transaction(function () use ($data, $userId) {
            $fundSource = FundSource::findOrFail($data['fund_source_id']);
            $sourceProject = Project::lockForUpdate()->findOrFail($data['source_project_id']);
            
            // 1. Calculate overall source project balance (Kas)
            $income = 0;
            $expense = 0;
            if ($sourceProject->is_isolated_cash) {
                $income = ProjectKasTransaction::where('project_id', $sourceProject->id)->sum('income');
                $expense = ProjectKasTransaction::where('project_id', $sourceProject->id)->sum('expense');
            } else {
                $income = Transaction::where('project_id', $sourceProject->id)->sum('income');
                $expense = Transaction::where('project_id', $sourceProject->id)->sum('expense');
            }
            
            $currentBalance = (float) $income - (float) $expense;
            $amount = (float) $data['amount'];
            
            if ($currentBalance < $amount) {
                $fmt = fn(float $v) => 'Rp ' . number_format($v, 0, ',', '.');
                throw new \Exception("Saldo project asal tidak mencukupi. Saldo tersedia: {$fmt($currentBalance)}, diminta: {$fmt($amount)}");
            }

            // 2. Calculate NET allocation constraint (Incoming - Outgoing for this specific fund source on this project)
            $incomingFs = FundMovement::where('fund_source_id', $fundSource->id)
                ->where('destination_project_id', $sourceProject->id)
                ->sum('amount'); // covers InitialAllocation + Transfer (in)
                
            $outgoingFs = FundMovement::where('fund_source_id', $fundSource->id)
                ->where('source_project_id', $sourceProject->id)
                ->sum('amount'); // covers Transfer (out) + Withdrawal
                
            $netAllocated = (float) $incomingFs - (float) $outgoingFs;
            
            if ($amount > $netAllocated) {
                $fmt = fn(float $v) => 'Rp ' . number_format($v, 0, ',', '.');
                throw new \Exception("Penarikan melebihi saldo bersih sumber modal ini di project. Sisa alokasi bersih tersedia: {$fmt($netAllocated)}, ditarik: {$fmt($amount)}");
            }
            
            // 3. Generate Reference
            $referenceNumber = $this->generateReferenceNumber();
            
            // 4. Insert Movement (Withdrawal = destination is null)
            $movement = FundMovement::create([
                'fund_source_id'         => $fundSource->id,
                'type'                   => FundMovementType::Withdrawal,
                'source_project_id'      => $sourceProject->id,
                'destination_project_id' => null,
                'amount'                 => $amount,
                'reference_number'       => $referenceNumber,
                'notes'                  => $data['notes'] ?? null,
                'created_by'             => $userId,
            ]);
            
            $date = $data['date'] ?? now()->format('Y-m-d');
            $paymentMethod = $data['payment_method'];
            
            // 5. Insert Expense for Source Project (NO income row anywhere)
            $description = "Penarikan Modal ke Sumber: {$fundSource->name} (Ref: {$referenceNumber})";
            if ($sourceProject->is_isolated_cash) {
                ProjectKasTransaction::create([
                    'project_id'       => $sourceProject->id,
                    'fund_movement_id' => $movement->id,
                    'user_id'          => $userId,
                    'date'             => $date,
                    'description'      => $description,
                    'payment_method'   => $paymentMethod,
                    'income'           => 0,
                    'expense'          => $amount,
                ]);
            } else {
                Transaction::create([
                    'project_id'       => $sourceProject->id,
                    'fund_movement_id' => $movement->id,
                    'account_id'       => $data['source_account_id'],
                    'user_id'          => $userId,
                    'date'             => $date,
                    'description'      => $description,
                    'payment_method'   => $paymentMethod,
                    'income'           => 0,
                    'expense'          => $amount,
                ]);
            }
            
            return $movement;
        });
    }
    /**
     * Reverse a previously-created FundMovement.
     *
     * Strategy per type:
     *   - InitialAllocation → createWithdrawal() from destination back to fund source
     *   - Transfer          → createTransfer() with source/destination swapped
     *   - Withdrawal        → createInitialAllocation() back to the original source project
     *
     * A movement can only be reversed once (guard via reversedBy()).
     */
    public function reverseMovement(FundMovement $original, int $userId): FundMovement
    {
        return DB::transaction(function () use ($original, $userId) {
            // Guard: already reversed?
            $existing = FundMovement::where('reversal_of_id', $original->id)->first();
            if ($existing) {
                throw new \Exception(
                    "Movement [{$original->reference_number}] sudah pernah dibatalkan " .
                    "(reversal: {$existing->reference_number})."
                );
            }

            // Guard: cannot reverse a reversal (prevent chains)
            if ($original->reversal_of_id !== null) {
                throw new \Exception(
                    "Movement [{$original->reference_number}] adalah pembatalan dari movement lain " .
                    "dan tidak bisa dibatalkan lagi."
                );
            }

            $amount    = (float) $original->amount;
            $pm        = 'rek'; // reversal always uses rek; date = today
            $date      = now()->format('Y-m-d');
            $notes     = "Pembatalan otomatis dari [{$original->reference_number}]";

            // We intercept FundMovement::create to set reversal_of_id afterward.
            // Each helper method returns the created movement, then we update it.

            switch ($original->type) {
                case FundMovementType::InitialAllocation:
                    // Reverse: withdraw the same amount from destination back to source
                    $reversal = $this->createWithdrawal([
                        'fund_source_id'    => $original->fund_source_id,
                        'source_project_id' => $original->destination_project_id,
                        'amount'            => $amount,
                        'payment_method'    => $pm,
                        'source_account_id' => null,
                        'date'              => $date,
                        'notes'             => $notes,
                    ], $userId);
                    break;

                case FundMovementType::Transfer:
                    // Reverse: swap source <-> destination (re-use createTransfer)
                    $reversal = $this->createTransfer([
                        'fund_source_id'          => $original->fund_source_id,
                        'source_project_id'        => $original->destination_project_id,
                        'destination_project_id'   => $original->source_project_id,
                        'amount'                   => $amount,
                        'payment_method'           => $pm,
                        'source_account_id'        => null,
                        'destination_account_id'   => null,
                        'date'                     => $date,
                        'notes'                    => $notes,
                    ], $userId);
                    break;

                case FundMovementType::Withdrawal:
                    // Reverse: re-allocate the same amount back to original source project
                    $reversal = $this->createInitialAllocation([
                        'fund_source_id'          => $original->fund_source_id,
                        'destination_project_id'  => $original->source_project_id,
                        'amount'                  => $amount,
                        'payment_method'          => $pm,
                        'account_id'              => null,
                        'date'                    => $date,
                        'notes'                   => $notes,
                    ], $userId);
                    break;

                default:
                    throw new \Exception("Tipe movement [{$original->type->value}] tidak dikenal.");
            }

            // Tag the reversal with reversal_of_id (cannot be set inside sub-methods
            // because they don't know about this context)
            $reversal->reversal_of_id = $original->id;
            $reversal->save();

            return $reversal;
        });
    }
}
