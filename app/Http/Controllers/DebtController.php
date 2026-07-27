<?php

namespace App\Http\Controllers;

use App\Models\Debt;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DebtController extends Controller
{
    #[OA\Get(
        path: "/api/debts",
        summary: "Daftar Hutang Piutang",
        tags: ["Debts"],
        responses: [
            new OA\Response(response: 200, description: "Berhasil")
        ]
    )]
    public function index()
    {
        $debts = Debt::with('user')->orderBy('trans_date', 'desc')->get();
        $debts->each->setAppends(['remaining_amount']);
        return response()->json($debts);
    }

    #[OA\Post(
        path: "/api/debts",
        summary: "Tambah Hutang Piutang",
        tags: ["Debts"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["person_name", "amount", "status", "trans_date"],
                properties: [
                    new OA\Property(property: "user_id", type: "integer", example: 1),
                    new OA\Property(property: "person_name", type: "string", example: "Mas Ryan"),
                    new OA\Property(property: "amount", type: "number", example: 5000000),
                    new OA\Property(property: "paid_amount", type: "number", example: 0),
                    new OA\Property(property: "status", type: "string", example: "belum lunas"),
                    new OA\Property(property: "trans_date", type: "string", format: "date", example: "2026-07-27")
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
            'person_name' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'paid_amount' => 'nullable|numeric',
            'status' => 'required|string|max:255',
            'trans_date' => 'required|date'
        ]);

        $debt = Debt::create($validated);
        $debt->load('user');
        $debt->setAppends(['remaining_amount']);
        return response()->json($debt, 201);
    }

    #[OA\Get(
        path: "/api/debts/{id}",
        summary: "Detail Hutang Piutang",
        tags: ["Debts"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Berhasil")
        ]
    )]
    public function show(Debt $debt)
    {
        $debt->load('user');
        $debt->setAppends(['remaining_amount']);
        return response()->json($debt);
    }

    #[OA\Put(
        path: "/api/debts/{id}",
        summary: "Update Hutang Piutang",
        tags: ["Debts"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "user_id", type: "integer"),
                    new OA\Property(property: "person_name", type: "string"),
                    new OA\Property(property: "amount", type: "number"),
                    new OA\Property(property: "paid_amount", type: "number"),
                    new OA\Property(property: "status", type: "string"),
                    new OA\Property(property: "trans_date", type: "string", format: "date")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Berhasil diupdate")
        ]
    )]
    public function update(Request $request, Debt $debt)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'person_name' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric',
            'paid_amount' => 'nullable|numeric',
            'status' => 'sometimes|required|string|max:255',
            'trans_date' => 'sometimes|required|date'
        ]);

        $debt->update($validated);
        $debt->load('user');
        $debt->setAppends(['remaining_amount']);
        return response()->json($debt);
    }

    #[OA\Delete(
        path: "/api/debts/{id}",
        summary: "Hapus Hutang Piutang",
        tags: ["Debts"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Berhasil dihapus")
        ]
    )]
    public function destroy(Debt $debt)
    {
        $debt->delete();
        return response()->noContent();
    }
}
