<?php

namespace App\Http\Controllers;

use App\Models\CashFlowPeriod;
use App\Models\CashFlowTransaction;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CashFlowTransactionController extends Controller
{
    #[OA\Post(
        path: "/api/cash-flow-periods/{period}/transactions",
        summary: "Tambah Transaksi Arus Kas",
        tags: ["Cash Flow Transactions"],
        parameters: [
            new OA\Parameter(name: "period", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["trans_date", "description", "source"],
                properties: [
                    new OA\Property(property: "trans_date", type: "string", format: "date", example: "2026-07-05"),
                    new OA\Property(property: "description", type: "string", example: "Pembelian ATK"),
                    new OA\Property(property: "source", type: "string", example: "CASH"),
                    new OA\Property(property: "out_amount", type: "number", example: 50000),
                    new OA\Property(property: "in_amount", type: "number", example: 0)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Berhasil dibuat")
        ]
    )]
    public function store(Request $request, CashFlowPeriod $cashFlowPeriod)
    {
        $validated = $request->validate([
            'trans_date' => 'required|date',
            'description' => 'required|string',
            'source' => 'required|string|max:255',
            'out_amount' => 'nullable|numeric',
            'in_amount' => 'nullable|numeric'
        ]);

        $transaction = $cashFlowPeriod->transactions()->create($validated);
        return response()->json($transaction, 201);
    }

    #[OA\Put(
        path: "/api/cash-flow-periods/{period}/transactions/{transaction}",
        summary: "Update Transaksi Arus Kas",
        tags: ["Cash Flow Transactions"],
        parameters: [
            new OA\Parameter(name: "period", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "transaction", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "trans_date", type: "string", format: "date"),
                    new OA\Property(property: "description", type: "string"),
                    new OA\Property(property: "source", type: "string"),
                    new OA\Property(property: "out_amount", type: "number"),
                    new OA\Property(property: "in_amount", type: "number")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Berhasil diupdate")
        ]
    )]
    public function update(Request $request, CashFlowPeriod $cashFlowPeriod, CashFlowTransaction $transaction)
    {
        $validated = $request->validate([
            'trans_date' => 'sometimes|required|date',
            'description' => 'sometimes|required|string',
            'source' => 'sometimes|required|string|max:255',
            'out_amount' => 'nullable|numeric',
            'in_amount' => 'nullable|numeric'
        ]);

        $transaction->update($validated);
        return response()->json($transaction);
    }

    #[OA\Delete(
        path: "/api/cash-flow-periods/{period}/transactions/{transaction}",
        summary: "Hapus Transaksi Arus Kas",
        tags: ["Cash Flow Transactions"],
        parameters: [
            new OA\Parameter(name: "period", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "transaction", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Berhasil dihapus")
        ]
    )]
    public function destroy(CashFlowPeriod $cashFlowPeriod, CashFlowTransaction $transaction)
    {
        $transaction->delete();
        return response()->noContent();
    }
}
