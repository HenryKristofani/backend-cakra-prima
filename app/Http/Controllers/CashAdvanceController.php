<?php

namespace App\Http\Controllers;

use App\Models\CashAdvance;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CashAdvanceController extends Controller
{
    #[OA\Get(
        path: "/api/cash-advances",
        summary: "Daftar Dana Talangan",
        tags: ["Cash Advances"],
        responses: [
            new OA\Response(response: 200, description: "Berhasil")
        ]
    )]
    public function index(Request $request)
    {
        $advances = CashAdvance::with('user')
            ->when($request->year, fn($q) => $q->whereYear('date_given', $request->year))
            ->when($request->month, fn($q) => $q->whereMonth('date_given', $request->month))
            ->orderBy('date_given', 'desc')
            ->get();
        return response()->json($advances);
    }

    #[OA\Post(
        path: "/api/cash-advances",
        summary: "Tambah Dana Talangan",
        tags: ["Cash Advances"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["recipient", "description", "amount", "date_given", "status"],
                properties: [
                    new OA\Property(property: "user_id", type: "integer", example: 1),
                    new OA\Property(property: "recipient", type: "string", example: "Pak Budi"),
                    new OA\Property(property: "description", type: "string", example: "Beli material"),
                    new OA\Property(property: "amount", type: "number", example: 1000000),
                    new OA\Property(property: "date_given", type: "string", format: "date", example: "2026-07-27"),
                    new OA\Property(property: "date_returned", type: "string", format: "date", example: null),
                    new OA\Property(property: "status", type: "string", example: "belum lunas")
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
            'user_id' => 'nullable|exists:users,id',
            'recipient' => 'required|string|max:255',
            'description' => 'required|string',
            'amount' => 'required|numeric',
            'date_given' => 'required|date',
            'date_returned' => 'nullable|date',
            'status' => 'required|string|max:255'
        ]);

        $advance = CashAdvance::create($validated);
        $advance->load('user');
        return response()->json($advance, 201);
    }

    #[OA\Get(
        path: "/api/cash-advances/{id}",
        summary: "Detail Dana Talangan",
        tags: ["Cash Advances"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Berhasil")
        ]
    )]
    public function show(CashAdvance $cashAdvance)
    {
        $cashAdvance->load('user');
        return response()->json($cashAdvance);
    }

    #[OA\Put(
        path: "/api/cash-advances/{id}",
        summary: "Update Dana Talangan",
        tags: ["Cash Advances"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "user_id", type: "integer"),
                    new OA\Property(property: "recipient", type: "string"),
                    new OA\Property(property: "description", type: "string"),
                    new OA\Property(property: "amount", type: "number"),
                    new OA\Property(property: "date_given", type: "string", format: "date"),
                    new OA\Property(property: "date_returned", type: "string", format: "date"),
                    new OA\Property(property: "status", type: "string")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Berhasil diupdate")
        ]
    )]
    public function update(Request $request, CashAdvance $cashAdvance)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'recipient' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'amount' => 'sometimes|required|numeric',
            'date_given' => 'sometimes|required|date',
            'date_returned' => 'nullable|date',
            'status' => 'sometimes|required|string|max:255'
        ]);

        $cashAdvance->update($validated);
        $cashAdvance->load('user');
        return response()->json($cashAdvance);
    }

    #[OA\Delete(
        path: "/api/cash-advances/{id}",
        summary: "Hapus Dana Talangan",
        tags: ["Cash Advances"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Berhasil dihapus")
        ]
    )]
    public function destroy(CashAdvance $cashAdvance)
    {
        $cashAdvance->delete();
        return response()->noContent();
    }
}
