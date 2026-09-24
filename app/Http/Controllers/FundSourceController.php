<?php

namespace App\Http\Controllers;

use App\Models\FundSource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FundSourceController extends Controller
{
    public function index(): JsonResponse
    {
        $sources = FundSource::withSum(['fundMovements as total_in' => function ($query) {
            $query->where('type', \App\Enums\FundMovementType::InitialAllocation->value);
        }], 'amount')
        ->withSum(['fundMovements as total_out' => function ($query) {
            $query->where('type', \App\Enums\FundMovementType::Withdrawal->value);
        }], 'amount')
        ->get();

        $sources->transform(function ($source) {
            $in = (float) ($source->total_in ?? 0);
            $out = (float) ($source->total_out ?? 0);
            $source->total_allocated = $in - $out;
            $source->remaining_unallocated = (float) $source->initial_amount - $source->total_allocated;
            // unset temporary attributes to keep response clean
            unset($source->total_in, $source->total_out);
            return $source;
        });

        return response()->json($sources);
    }

    public function breakdown(int $id): JsonResponse
    {
        $fundSource = FundSource::findOrFail($id);

        $sql = "
            SELECT 
                p.id, 
                p.name, 
                (COALESCE(i.total_in, 0) - COALESCE(o.total_out, 0)) as current_amount
            FROM projects p
            LEFT JOIN (
                SELECT destination_project_id, SUM(amount) as total_in
                FROM fund_movements
                WHERE fund_source_id = ?
                GROUP BY destination_project_id
            ) i ON i.destination_project_id = p.id
            LEFT JOIN (
                SELECT source_project_id, SUM(amount) as total_out
                FROM fund_movements
                WHERE fund_source_id = ? AND source_project_id IS NOT NULL
                GROUP BY source_project_id
            ) o ON o.source_project_id = p.id
            WHERE (COALESCE(i.total_in, 0) - COALESCE(o.total_out, 0)) > 0
        ";

        $results = \Illuminate\Support\Facades\DB::select($sql, [$id, $id]);

        $breakdown = array_map(function ($row) use ($fundSource) {
            $currentAmount = (float) $row->current_amount;
            $initialAmount = (float) $fundSource->initial_amount;
            return [
                'project_id' => $row->id,
                'project_name' => $row->name,
                'current_amount' => $currentAmount,
                'percentage' => $initialAmount > 0 ? round(($currentAmount / $initialAmount) * 100, 2) : 0
            ];
        }, $results);

        return response()->json([
            'fund_source' => $fundSource,
            'breakdown' => $breakdown
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string',
            'type'           => 'required|string', // HutangBank, ModalInvestor, etc.
            'initial_amount' => 'required|numeric',
            'notes'          => 'nullable|string',
            'is_active'      => 'boolean'
        ]);

        $fundSource = FundSource::create($validated);

        return response()->json([
            'message' => 'Fund source berhasil dibuat.',
            'data'    => $fundSource,
        ], 201);
    }
    public function update(Request $request, FundSource $fundSource): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'sometimes|string',
            'type'           => 'sometimes|string',
            'initial_amount' => 'sometimes|numeric',
            'notes'          => 'nullable|string',
            'is_active'      => 'sometimes|boolean',
        ]);

        // Guard: initial_amount cannot be reduced below already-allocated amount
        if (isset($validated['initial_amount'])) {
            $totalIn = (float) $fundSource->fundMovements()
                ->where('type', \App\Enums\FundMovementType::InitialAllocation->value)
                ->sum('amount');
            $totalOut = (float) $fundSource->fundMovements()
                ->where('type', \App\Enums\FundMovementType::Withdrawal->value)
                ->sum('amount');
            $totalAllocated = $totalIn - $totalOut;

            if ((float) $validated['initial_amount'] < $totalAllocated) {
                $fmt = fn(float $v) => 'Rp ' . number_format($v, 0, ',', '.');
                return response()->json([
                    'message' => "initial_amount tidak boleh lebih kecil dari total yang sudah teralokasi " .
                                 "({$fmt($totalAllocated)}). Nilai minimal adalah {$fmt($totalAllocated)}.",
                ], 422);
            }
        }

        $fundSource->update($validated);

        return response()->json([
            'message' => 'Fund source berhasil diperbarui.',
            'data'    => $fundSource,
        ]);
    }
}
