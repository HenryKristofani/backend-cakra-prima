<?php

namespace App\Http\Controllers;

use App\Models\DebtGroup;
use App\Models\DebtPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DebtPaymentController extends Controller
{
    #[OA\Post(
        path: "/api/debt-groups/{debtGroup}/payments",
        summary: "Tambah Pembayaran Hutang",
        tags: ["Debt Payments"],
        parameters: [
            new OA\Parameter(name: "debtGroup", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["description", "amount"],
                properties: [
                    new OA\Property(property: "description", type: "string", example: "Cicil bayar bulan pertama"),
                    new OA\Property(property: "payment_date", type: "string", format: "date", example: "2026-07-23"),
                    new OA\Property(property: "amount", type: "number", example: 500000)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201, 
                description: "Berhasil ditambahkan",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Pembayaran berhasil ditambahkan."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function store(Request $request, DebtGroup $debtGroup): JsonResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'payment_date' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $payment = $debtGroup->payments()->create($validated);
        $debtGroup->recalculate();

        return response()->json([
            'message' => 'Pembayaran berhasil ditambahkan.',
            'data' => $payment
        ], 201);
    }

    #[OA\Put(
        path: "/api/debt-groups/{debtGroup}/payments/{debtPayment}",
        summary: "Update Pembayaran Hutang",
        tags: ["Debt Payments"],
        parameters: [
            new OA\Parameter(name: "debtGroup", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "debtPayment", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["description", "amount"],
                properties: [
                    new OA\Property(property: "description", type: "string", example: "Cicil bayar revisi"),
                    new OA\Property(property: "payment_date", type: "string", format: "date", example: "2026-07-24"),
                    new OA\Property(property: "amount", type: "number", example: 550000)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200, 
                description: "Berhasil diperbarui",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Pembayaran berhasil diperbarui."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function update(Request $request, DebtGroup $debtGroup, DebtPayment $debtPayment): JsonResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'payment_date' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $debtPayment->update($validated);
        $debtGroup->recalculate();

        return response()->json([
            'message' => 'Pembayaran berhasil diperbarui.',
            'data' => $debtPayment
        ]);
    }

    #[OA\Delete(
        path: "/api/debt-groups/{debtGroup}/payments/{debtPayment}",
        summary: "Hapus Pembayaran Hutang",
        tags: ["Debt Payments"],
        parameters: [
            new OA\Parameter(name: "debtGroup", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "debtPayment", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200, 
                description: "Berhasil dihapus",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Pembayaran berhasil dihapus.")
                    ]
                )
            )
        ]
    )]
    public function destroy(DebtGroup $debtGroup, DebtPayment $debtPayment): JsonResponse
    {
        $debtPayment->delete();
        $debtGroup->recalculate();

        return response()->json([
            'message' => 'Pembayaran berhasil dihapus.'
        ]);
    }
}