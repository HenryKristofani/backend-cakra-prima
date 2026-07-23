<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
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
        // Step 1: Fetch ALL rows ascending to calculate accurate running balance
        $allTransactions = Transaction::query()
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // Step 2: Calculate running balance for every row
        $runningBalance = 0;
        $allTransactions = $allTransactions->map(function ($trx) use (&$runningBalance) {
            $runningBalance += (float) $trx->income - (float) $trx->expense;
            $trx->rekap_saldo = $runningBalance;
            return $trx;
        });

        // Step 3: Reverse to descending (newest on top), running balance already baked in
        $allTransactions = $allTransactions->reverse()->values();

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
            'date' => 'required|date',
            'company' => 'required|string',
            'description' => 'required|string',
            'payment_method' => 'required|in:cash,rek',
            'income' => 'nullable|numeric',
            'expense' => 'nullable|numeric',
        ]);

        return Transaction::create($validated);
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
    public function update(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'date' => 'sometimes|date',
            'company' => 'sometimes|string',
            'description' => 'sometimes|string',
            'payment_method' => 'sometimes|in:cash,rek',
            'income' => 'nullable|numeric',
            'expense' => 'nullable|numeric',
        ]);

        $transaction->update($validated);
        return $transaction;
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
    public function destroy(Transaction $transaction)
    {
        $transaction->delete();
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
        public function summary()
        {
            $bulanIni = now();

            $result = Transaction::selectRaw("
                SUM(income) as total_income,
                SUM(expense) as total_expense,
                SUM(CASE WHEN EXTRACT(MONTH FROM date) = ? AND EXTRACT(YEAR FROM date) = ? THEN income ELSE 0 END) as pemasukan_bulan_ini,
                SUM(CASE WHEN EXTRACT(MONTH FROM date) = ? AND EXTRACT(YEAR FROM date) = ? THEN expense ELSE 0 END) as pengeluaran_bulan_ini,
                SUM(CASE WHEN payment_method = 'cash' THEN income ELSE 0 END) as cash_income,
                SUM(CASE WHEN payment_method = 'cash' THEN expense ELSE 0 END) as cash_expense
            ", [$bulanIni->month, $bulanIni->year, $bulanIni->month, $bulanIni->year])
            ->first();

            return response()->json([
                'total_saldo_kas' => (float) $result->total_income - (float) $result->total_expense,
                'pemasukan_bulan_ini' => (float) $result->pemasukan_bulan_ini,
                'pengeluaran_bulan_ini' => (float) $result->pengeluaran_bulan_ini,
                'total_saldo_cash' => (float) $result->cash_income - (float) $result->cash_expense,
            ]);
        }
}
