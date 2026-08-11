<?php

namespace App\Http\Controllers;

use App\Exports\TransactionExport;
use App\Models\Transaction;
use App\Models\ProjectKasTransaction;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use OpenApi\Attributes as OA;

class TransactionController extends Controller
{
    #[OA\Get(
        path: "/api/transactions",
        summary: "Daftar transaksi",
        tags: ["Transactions"],
        responses: [
            new OA\Response(response: 200, description: "Berhasil")
        ]
    )]
    public function index(Request $request)
    {
        $projectId = $request->input('project_id');
        $isIsolated = false;

        if ($projectId) {
            $project = Project::find($projectId);
            if ($project && $project->is_isolated_cash) {
                $isIsolated = true;
            }
        }

        // Step 1: Fetch ALL rows ascending to calculate accurate running balance
        if ($isIsolated) {
            $allTransactions = ProjectKasTransaction::with(['project', 'user', 'rapItem:id,description'])
                ->where('project_id', $projectId)
                ->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->get();
        } else {
            $allTransactions = Transaction::with(['project', 'account', 'user', 'rapItem:id,description'])
                ->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->get();
        }

        // Step 2: Calculate running balance for every row
        $runningBalance = 0;
        $allTransactions = $allTransactions->map(function ($trx) use (&$runningBalance, $isIsolated) {
            $runningBalance += (float) $trx->income - (float) $trx->expense;
            $trx->rekap_saldo = $runningBalance;
            if ($isIsolated) {
                $trx->account_id = null;
                $trx->account = null;
            }
            return $trx;
        });

        // Step 3: Keep ascending order (oldest to newest by date)

        // Step 4: Apply year/month filter AFTER running balance is computed
        if ($request->filled('year')) {
            $allTransactions = $allTransactions->filter(function ($trx) use ($request) {
                return date('Y', strtotime($trx->date)) == $request->year;
            })->values();
        }
        if ($request->filled('month')) {
            $allTransactions = $allTransactions->filter(function ($trx) use ($request) {
                return date('n', strtotime($trx->date)) == $request->month;
            })->values();
        }
        if (!$isIsolated && $request->filled('project_id')) {
            $allTransactions = $allTransactions->filter(function ($trx) use ($request) {
                return $trx->project_id == $request->project_id;
            })->values();
        }

        // Step 5: Manually paginate the in-memory collection
        $perPage = 5;
        $currentPage = $request->input('page', 1);
        $total = $allTransactions->count();
        $items = $allTransactions->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return response()->json([
            'current_page' => (int) $currentPage,
            'data' => $items,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage),
            'from' => $total > 0 ? ($currentPage - 1) * $perPage + 1 : null,
            'to' => $total > 0 ? min($currentPage * $perPage, $total) : null,
        ]);
    }

    #[OA\Post(
        path: "/api/projects/{project}/transactions",
        summary: "Tambah transaksi nested untuk project",
        tags: ["Transactions"],
        responses: [
            new OA\Response(response: 201, description: "Berhasil dibuat")
        ]
    )]
    public function storeNested(Request $request, Project $project)
    {
        $validated = $request->validate([
            'date'          => 'required|date',
            'account_id'    => 'nullable|exists:accounts,id',
            'user_id'       => 'nullable|exists:users,id',
            'rap_item_id'   => 'nullable|exists:rap_items,id',
            'company'       => 'nullable|string',
            'description'   => 'required|string',
            'payment_method'=> 'required|in:cash,rek',
            'income'        => 'nullable|numeric',
            'expense'       => 'nullable|numeric',
        ]);

        $validated['project_id'] = $project->id;

        if ($project->is_isolated_cash) {
            unset($validated['account_id']);
            $trx = ProjectKasTransaction::create($validated);
            $trx->load(['project', 'user', 'rapItem:id,description']);
            $trx->account_id = null;
            $trx->account = null;
            return $trx;
        } else {
            $trx = Transaction::create($validated);
            return $trx->load(['project', 'account', 'user', 'rapItem:id,description']);
        }
    }

    #[OA\Post(
        path: "/api/transactions",
        summary: "Tambah transaksi baru",
        tags: ["Transactions"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["date", "company", "description", "payment_method"],
                properties: [
                    new OA\Property(property: "date", type: "string", format: "date", example: "2026-07-22"),
                    new OA\Property(property: "company", type: "string", example: "Cakra Prima"),
                    new OA\Property(property: "description", type: "string", example: "Pembelian ATK"),
                    new OA\Property(property: "payment_method", type: "string", enum: ["cash", "rek"], example: "cash"),
                    new OA\Property(property: "income", type: "number", example: 500000),
                    new OA\Property(property: "expense", type: "number", example: 0),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Berhasil dibuat")
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date'          => 'required|date',
            'account_id'    => 'nullable|exists:accounts,id',
            'project_id'    => 'nullable|exists:projects,id',
            'user_id'       => 'nullable|exists:users,id',
            'rap_item_id'   => 'nullable|exists:rap_items,id',
            'company'       => 'nullable|string',
            'description'   => 'required|string',
            'payment_method'=> 'required|in:cash,rek',
            'income'        => 'nullable|numeric',
            'expense'       => 'nullable|numeric',
        ]);

        $projectId = $request->input('project_id');
        if ($projectId) {
            $project = Project::find($projectId);
            if ($project && $project->is_isolated_cash) {
                unset($validated['account_id']);
                $trx = ProjectKasTransaction::create($validated);
                $trx->load(['project', 'user', 'rapItem:id,description']);
                $trx->account_id = null;
                $trx->account = null;
                return $trx;
            }
        }

        $trx = Transaction::create($validated);
        return $trx->load(['project', 'account', 'user', 'rapItem:id,description']);
    }

    #[OA\Put(
        path: "/api/transactions/{id}",
        summary: "Update transaksi",
        tags: ["Transactions"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "date", type: "string", format: "date", example: "2026-07-22"),
                    new OA\Property(property: "company", type: "string", example: "Cakra Prima"),
                    new OA\Property(property: "description", type: "string", example: "Pembelian ATK"),
                    new OA\Property(property: "payment_method", type: "string", enum: ["cash", "rek"], example: "cash"),
                    new OA\Property(property: "income", type: "number", example: 500000),
                    new OA\Property(property: "expense", type: "number", example: 0),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Berhasil diupdate")
        ]
    )]
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'date'          => 'sometimes|date',
            'account_id'    => 'nullable|exists:accounts,id',
            'project_id'    => 'nullable|exists:projects,id',
            'user_id'       => 'nullable|exists:users,id',
            'rap_item_id'   => 'nullable|exists:rap_items,id',
            'company'       => 'nullable|string',
            'description'   => 'sometimes|string',
            'payment_method'=> 'sometimes|in:cash,rek',
            'income'        => 'nullable|numeric',
            'expense'       => 'nullable|numeric',
        ]);

        $projectId = $request->input('project_id');
        $isIsolated = false;

        if ($projectId) {
            $project = Project::find($projectId);
            if ($project && $project->is_isolated_cash) {
                $isIsolated = true;
            }
        }

        if ($isIsolated) {
            $transaction = ProjectKasTransaction::findOrFail($id);
            unset($validated['account_id']);
            $transaction->update($validated);
            $transaction->load(['project', 'user', 'rapItem:id,description']);
            $transaction->account_id = null;
            $transaction->account = null;
            return $transaction;
        } else {
            $transaction = Transaction::findOrFail($id);
            $transaction->update($validated);
            return $transaction->load(['project', 'account', 'user', 'rapItem:id,description']);
        }
    }

    #[OA\Delete(
        path: "/api/transactions/{id}",
        summary: "Hapus transaksi",
        tags: ["Transactions"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Berhasil dihapus")
        ]
    )]
    public function destroy(Request $request, $id)
    {
        $projectId = $request->input('project_id');
        $isIsolated = false;

        if ($projectId) {
            $project = Project::find($projectId);
            if ($project && $project->is_isolated_cash) {
                $isIsolated = true;
            }
        }

        if ($isIsolated) {
            $transaction = ProjectKasTransaction::findOrFail($id);
            $transaction->delete();
        } else {
            $transaction = Transaction::findOrFail($id);
            $transaction->delete();
        }
        
        return response()->noContent();
    }

    #[OA\Get(
        path: "/api/transactions-summary",
        summary: "Ringkasan kas & keuangan",
        tags: ["Transactions"],
        responses: [
            new OA\Response(response: 200, description: "Berhasil")
        ]
    )]
    public function summary(Request $request)
    {
        $projectId = $request->input('project_id');
        $isIsolated = false;

        if ($projectId) {
            $project = Project::find($projectId);
            if ($project && $project->is_isolated_cash) {
                $isIsolated = true;
            }
        }

        if ($isIsolated) {
            $baseQuery = ProjectKasTransaction::query()->where('project_id', $projectId);
        } else {
            $baseQuery = Transaction::query();
            if ($projectId) {
                $baseQuery->where('project_id', $projectId);
            }
        }

        $totalIncome = (clone $baseQuery)->sum('income');
        $totalExpense = (clone $baseQuery)->sum('expense');
        $cashIncome = (clone $baseQuery)
            ->where('payment_method', 'cash')
            ->sum('income');
        $cashExpense = (clone $baseQuery)
            ->where('payment_method', 'cash')
            ->sum('expense');

        $periodQuery = (clone $baseQuery)
            ->when($request->filled('year'), fn ($query) => $query->whereYear('date', $request->input('year')))
            ->when($request->filled('month'), fn ($query) => $query->whereMonth('date', $request->input('month')));

        $pemasukan = (clone $periodQuery)->sum('income');
        $pengeluaran = (clone $periodQuery)->sum('expense');

        return response()->json([
            'total_saldo_kas' => (float) $totalIncome - (float) $totalExpense,
            'pemasukan_bulan_ini' => (float) $pemasukan,
            'pengeluaran_bulan_ini' => (float) $pengeluaran,
            'total_saldo_cash' => (float) $cashIncome - (float) $cashExpense,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $projectId = $request->input('project_id');
        $isIsolated = false;

        if ($projectId) {
            $project = Project::find($projectId);
            if ($project && $project->is_isolated_cash) {
                $isIsolated = true;
            }
        }

        // Fetch ALL rows ascending for accurate running balance
        if ($isIsolated) {
            $allTransactions = ProjectKasTransaction::with(['project', 'user', 'rapItem:id,description'])
                ->where('project_id', $projectId)
                ->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->get();
        } else {
            $allTransactions = Transaction::with(['project', 'account', 'user', 'rapItem:id,description'])
                ->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->get();
        }
        
        $runningBalance = 0;
        $allTransactions = $allTransactions->map(function ($trx) use (&$runningBalance, $isIsolated) {
            $runningBalance += (float) $trx->income - (float) $trx->expense;
            $trx->rekap_saldo = $runningBalance;
            if ($isIsolated) {
                $trx->account_id = null;
                $trx->account = null;
            }
            return $trx;
        });

        // Apply year/month filter
        if ($request->filled('year')) {
            $allTransactions = $allTransactions->filter(fn($t) =>
                Carbon::parse($t->date)->year == $request->year
            )->values();
        }
        if ($request->filled('month')) {
            $allTransactions = $allTransactions->filter(fn($t) =>
                Carbon::parse($t->date)->month == $request->month
            )->values();
        }
        if (!$isIsolated && $request->filled('project_id')) {
            $allTransactions = $allTransactions->filter(function ($trx) use ($request) {
                return $trx->project_id == $request->project_id;
            })->values();
        }

        // Build period label
        if ($request->filled('year') && $request->filled('month')) {
            $start = Carbon::create($request->year, $request->month, 1);
            $end = $start->copy()->endOfMonth();
            $periodLabel = $start->translatedFormat('d F Y') . ' - ' . $end->translatedFormat('d F Y');
        } elseif ($request->filled('year')) {
            $periodLabel = '01 Januari ' . $request->year . ' - 31 Desember ' . $request->year;
        } else {
            $periodLabel = '01 Januari ' . now()->year . ' - 31 Desember ' . now()->year;
        }

        // Use UPPERCASE month names in Indonesian
        $periodLabel = strtoupper($periodLabel);

        $klasifikasi = $request->input('klasifikasi', 'SEMUA');
        $filename = 'laporan-arus-kas-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(
            new TransactionExport($allTransactions, $periodLabel, $klasifikasi),
            $filename
        );
    }
}
