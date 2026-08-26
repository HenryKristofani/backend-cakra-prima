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
        path: "/api/projects/{project}/transactions/bulk",
        summary: "Tambah transaksi nested untuk project secara bulk",
        tags: ["Transactions"],
        responses: [
            new OA\Response(response: 201, description: "Berhasil dibuat")
        ]
    )]
    public function bulkStore(Request $request, Project $project)
    {
        $validated = $request->validate([
            'items'                  => 'required|array|min:1',
            'items.*.date'           => 'required|date',
            'items.*.account_id'     => 'nullable|exists:accounts,id',
            'items.*.user_id'        => 'nullable|exists:users,id',
            'items.*.rap_item_id'    => 'nullable|exists:rap_items,id',
            'items.*.company'        => 'nullable|string',
            'items.*.description'    => 'required|string',
            'items.*.payment_method' => 'required|in:cash,rek',
            'items.*.income'         => 'nullable|numeric',
            'items.*.expense'        => 'nullable|numeric',
        ]);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $project) {
            $createdItems = [];
            foreach ($validated['items'] as $itemData) {
                $itemData['project_id'] = $project->id;
                if ($project->is_isolated_cash) {
                    unset($itemData['account_id']);
                    $trx = ProjectKasTransaction::create($itemData);
                    $trx->load(['project', 'user', 'rapItem:id,description']);
                    $trx->account_id = null;
                    $trx->account = null;
                    $createdItems[] = $trx;
                } else {
                    $trx = Transaction::create($itemData);
                    $trx->load(['project', 'account', 'user', 'rapItem:id,description']);
                    $createdItems[] = $trx;
                }
            }
            return response()->json($createdItems, 201);
        });
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

    #[OA\Post(
        path: "/api/transactions/bulk",
        summary: "Tambah banyak transaksi sekaligus (Global)",
        tags: ["Transactions"],
        responses: [
            new OA\Response(response: 201, description: "Berhasil")
        ]
    )]
    public function bulkStoreGlobal(Request $request)
    {
        $validated = $request->validate([
            'items'                  => 'required|array|min:1',
            'items.*.project_id'     => 'nullable|exists:projects,id',
            'items.*.date'           => 'required|date',
            'items.*.account_id'     => 'nullable|exists:accounts,id',
            'items.*.user_id'        => 'nullable|exists:users,id',
            'items.*.rap_item_id'    => 'nullable|exists:rap_items,id',
            'items.*.company'        => 'nullable|string',
            'items.*.description'    => 'required|string',
            'items.*.payment_method' => 'required|in:cash,rek',
            'items.*.income'         => 'nullable|numeric',
            'items.*.expense'        => 'nullable|numeric',
        ]);

        $batchIsolated = [];
        $batchGlobal = [];

        foreach ($validated['items'] as $itemData) {
            $projectId = $itemData['project_id'] ?? null;
            $isIsolated = false;
            
            if ($projectId) {
                $project = Project::find($projectId);
                if ($project && $project->is_isolated_cash) {
                    $isIsolated = true;
                }
            }

            // Set user_id automatically
            $itemData['user_id'] = $itemData['user_id'] ?? $request->user()?->id ?? 1;

            if ($isIsolated) {
                $batchIsolated[] = $itemData;
            } else {
                $batchGlobal[] = $itemData;
            }
        }

        $createdItems = [];

        if (count($batchIsolated) > 0) {
            $createdIsolated = \Illuminate\Support\Facades\DB::transaction(function () use ($batchIsolated) {
                $results = [];
                foreach ($batchIsolated as $itemData) {
                    unset($itemData['account_id']);
                    $trx = ProjectKasTransaction::create($itemData);
                    $trx->load(['project', 'user', 'rapItem:id,description']);
                    $results[] = $trx;
                }
                return $results;
            });
            $createdItems = array_merge($createdItems, $createdIsolated);
        }

        if (count($batchGlobal) > 0) {
            $createdGlobal = \Illuminate\Support\Facades\DB::transaction(function () use ($batchGlobal) {
                $results = [];
                foreach ($batchGlobal as $itemData) {
                    unset($itemData['company']);
                    $trx = Transaction::create($itemData);
                    $trx->load(['project', 'account', 'user', 'rapItem:id,description']);
                    $results[] = $trx;
                }
                return $results;
            });
            $createdItems = array_merge($createdItems, $createdGlobal);
        }

        return response()->json($createdItems, 201);
    }

    #[OA\Put(
        path: "/api/transactions/bulk",
        summary: "Update transaksi secara bulk",
        tags: ["Transactions"],
        responses: [
            new OA\Response(response: 200, description: "Berhasil diupdate")
        ]
    )]
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'items'                  => 'required|array|min:1',
            'items.*.id'             => 'required|integer',
            'items.*.project_id'     => 'nullable|exists:projects,id',
            'items.*.date'           => 'sometimes|date',
            'items.*.account_id'     => 'nullable|exists:accounts,id',
            'items.*.user_id'        => 'nullable|exists:users,id',
            'items.*.rap_item_id'    => 'nullable|exists:rap_items,id',
            'items.*.company'        => 'nullable|string',
            'items.*.description'    => 'sometimes|string',
            'items.*.payment_method' => 'sometimes|in:cash,rek',
            'items.*.income'         => 'nullable|numeric',
            'items.*.expense'        => 'nullable|numeric',
        ]);

        $batchIsolated = [];
        $batchGlobal = [];

        foreach ($validated['items'] as $itemData) {
            $projectId = $itemData['project_id'] ?? null;
            $isIsolated = false;
            
            if ($projectId) {
                $project = Project::find($projectId);
                if ($project && $project->is_isolated_cash) {
                    $isIsolated = true;
                }
            }

            if ($isIsolated) {
                $batchIsolated[] = $itemData;
            } else {
                $batchGlobal[] = $itemData;
            }
        }

        $updatedItems = [];

        if (count($batchIsolated) > 0) {
            $updatedIsolated = \Illuminate\Support\Facades\DB::transaction(function () use ($batchIsolated) {
                $results = [];
                foreach ($batchIsolated as $itemData) {
                    $transaction = ProjectKasTransaction::findOrFail($itemData['id']);
                    $updateData = $itemData;
                    unset($updateData['id']);
                    unset($updateData['account_id']);
                    
                    $transaction->update($updateData);
                    $transaction->load(['project', 'user', 'rapItem:id,description']);
                    $transaction->account_id = null;
                    $transaction->account = null;
                    $results[] = $transaction;
                }
                return $results;
            });
            $updatedItems = array_merge($updatedItems, $updatedIsolated);
        }

        if (count($batchGlobal) > 0) {
            $updatedGlobal = \Illuminate\Support\Facades\DB::transaction(function () use ($batchGlobal) {
                $results = [];
                foreach ($batchGlobal as $itemData) {
                    $transaction = Transaction::findOrFail($itemData['id']);
                    $updateData = $itemData;
                    unset($updateData['id']);
                    unset($updateData['company']);
                    
                    $transaction->update($updateData);
                    $transaction->load(['project', 'account', 'user', 'rapItem:id,description']);
                    $results[] = $transaction;
                }
                return $results;
            });
            $updatedItems = array_merge($updatedItems, $updatedGlobal);
        }

        return response()->json($updatedItems, 200);
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

        \Illuminate\Support\Facades\Log::info("DELETE Transaction Hit", ['id' => $id, 'project_id' => $projectId, 'is_isolated' => $isIsolated]);

        if ($isIsolated) {
            $transaction = ProjectKasTransaction::findOrFail($id);
            $transaction->delete();
        } else {
            $transaction = Transaction::findOrFail($id);
            $transaction->delete();
        }
        
        \Illuminate\Support\Facades\Log::info("DELETE Transaction Success", ['id' => $id]);
        return response()->json(['message' => 'Berhasil dihapus'], 200);
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

    // ─────────────────────────────────────────────────────────────────────────────
    // POST /transactions/import/preview
    // Parse uploaded file, validate rows, return preview WITHOUT inserting to DB
    // ─────────────────────────────────────────────────────────────────────────────

    public function importPreview(Request $request)
    {
        $request->validate([
            'file'   => 'required|file|mimes:xlsx,xls,csv|max:5120',
            'format' => 'nullable|string|in:new,legacy',
        ]);

        $format = $request->input('format', 'new');

        if ($format === 'legacy') {
            $request->validate([
                'project_id'      => 'nullable|integer|exists:projects,id',
                'cash_account_id' => 'nullable|integer|exists:accounts,id',
                'rek_account_id'  => 'nullable|integer|exists:accounts,id',
            ]);

            $project = $request->filled('project_id') ? Project::find($request->input('project_id')) : null;
            
            $import = new \App\Imports\LegacyTransactionImport(
                $request->input('project_id'),
                $request->input('cash_account_id'),
                $request->input('rek_account_id'),
                $project ? $project->is_isolated_cash : false
            );
        } else {
            $import = new \App\Imports\TransactionImport();
        }

        Excel::import($import, $request->file('file'));

        $rows = $import->getParsedRows();
        $validCount   = count(array_filter($rows, fn($r) => $r['is_valid']));
        $errorCount   = count($rows) - $validCount;

        return response()->json([
            'rows'          => $rows,
            'total_rows'    => count($rows),
            'valid_count'   => $validCount,
            'error_count'   => $errorCount,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // POST /transactions/import/confirm
    // Receive array of validated row data, insert to correct table
    // ─────────────────────────────────────────────────────────────────────────────

    public function importConfirm(Request $request)
    {
        $request->validate([
            'rows'                  => 'required|array|min:1',
            'rows.*.date'           => 'required|date',
            'rows.*.description'    => 'required|string',
            'rows.*.payment_method' => 'required|in:cash,rek',
            'rows.*.income'         => 'nullable|numeric|min:0',
            'rows.*.expense'        => 'nullable|numeric|min:0',
            'rows.*.account_id'     => 'nullable|integer|exists:accounts,id',
            'rows.*.project_id'     => 'nullable|integer|exists:projects,id',
            'rows.*.rap_item_id'    => 'nullable|integer|exists:rap_items,id',
            'rows.*.company'        => 'nullable|string',
            'rows.*._is_isolated'   => 'nullable|boolean',
        ]);

        $batchIsolated = [];
        $batchGlobal   = [];

        foreach ($request->rows as $rowData) {
            $isIsolated = (bool) ($rowData['_is_isolated'] ?? false);

            // Sanitise the row – remove internal flag before insert
            $insertData = collect($rowData)->except(['_is_isolated'])->all();
            $insertData['user_id'] = $request->user()?->id ?? 1;

            if ($isIsolated) {
                // account_id is irrelevant for isolated kas
                unset($insertData['account_id']);
                $batchIsolated[] = $insertData;
            } else {
                // company does not exist in transactions table
                unset($insertData['company']);
                $batchGlobal[] = $insertData;
            }
        }

        $createdCount = 0;

        \Illuminate\Support\Facades\DB::transaction(function () use ($batchIsolated, $batchGlobal, &$createdCount) {
            foreach ($batchIsolated as $itemData) {
                ProjectKasTransaction::create($itemData);
                $createdCount++;
            }
            foreach ($batchGlobal as $itemData) {
                Transaction::create($itemData);
                $createdCount++;
            }
        });

        return response()->json([
            'message'       => "{$createdCount} transaksi berhasil diimport",
            'imported_count'=> $createdCount,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // GET /transactions/import/template
    // Download a blank import template (.xlsx) showing the correct column structure
    // ─────────────────────────────────────────────────────────────────────────────

    public function importTemplate()
    {
        $filename = 'template-import-kas.xlsx';
        return Excel::download(new \App\Exports\TransactionImportTemplate(), $filename);
    }
}

