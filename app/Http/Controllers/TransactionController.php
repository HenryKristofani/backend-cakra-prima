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
        $query = Transaction::query()->orderBy('date', 'desc');

        if ($request->filled('year')) {
            $query->whereYear('date', $request->year);
        }
        if ($request->filled('month')) {
            $query->whereMonth('date', $request->month);
        }

        return $query->paginate(5);
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
