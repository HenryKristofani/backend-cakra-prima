<?php

namespace App\Http\Controllers;

use App\Models\Installment;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class InstallmentController extends Controller
{
    #[OA\Get(
        path: "/api/installments",
        summary: "Daftar Angsuran",
        tags: ["Installments"],
        responses: [
            new OA\Response(response: 200, description: "Berhasil")
        ]
    )]
    public function index(Request $request)
    {
        $installments = Installment::with('user')
            ->when($request->year, fn($q) => $q->whereYear('due_date', $request->year))
            ->when($request->month, fn($q) => $q->whereMonth('due_date', $request->month))
            ->orderBy('due_date', 'asc')
            ->get();
        return response()->json($installments);
    }

    #[OA\Post(
        path: "/api/installments",
        summary: "Tambah Angsuran",
        tags: ["Installments"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["category", "due_date", "amount", "status"],
                properties: [
                    new OA\Property(property: "user_id", type: "integer", example: 1),
                    new OA\Property(property: "category", type: "string", example: "BPJS"),
                    new OA\Property(property: "due_date", type: "string", format: "date", example: "2026-07-31"),
                    new OA\Property(property: "amount", type: "number", example: 150000),
                    new OA\Property(property: "status", type: "string", example: "belum dibayar")
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
            'category' => 'required|string|max:255',
            'due_date' => 'required|date',
            'amount' => 'required|numeric',
            'status' => 'required|string|max:255'
        ]);

        $installment = Installment::create($validated);
        $installment->load('user');
        return response()->json($installment, 201);
    }

    #[OA\Get(
        path: "/api/installments/{id}",
        summary: "Detail Angsuran",
        tags: ["Installments"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Berhasil")
        ]
    )]
    public function show(Installment $installment)
    {
        $installment->load('user');
        return response()->json($installment);
    }

    #[OA\Put(
        path: "/api/installments/{id}",
        summary: "Update Angsuran",
        tags: ["Installments"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "user_id", type: "integer"),
                    new OA\Property(property: "category", type: "string"),
                    new OA\Property(property: "due_date", type: "string", format: "date"),
                    new OA\Property(property: "amount", type: "number"),
                    new OA\Property(property: "status", type: "string")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Berhasil diupdate")
        ]
    )]
    public function update(Request $request, Installment $installment)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'category' => 'sometimes|required|string|max:255',
            'due_date' => 'sometimes|required|date',
            'amount' => 'sometimes|required|numeric',
            'status' => 'sometimes|required|string|max:255'
        ]);

        $installment->update($validated);
        $installment->load('user');
        return response()->json($installment);
    }

    #[OA\Delete(
        path: "/api/installments/{id}",
        summary: "Hapus Angsuran",
        tags: ["Installments"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Berhasil dihapus")
        ]
    )]
    public function destroy(Installment $installment)
    {
        $installment->delete();
        return response()->noContent();
    }
}
