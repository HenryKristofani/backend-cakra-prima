<?php

namespace App\Http\Controllers;

use App\Models\Potential;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PotentialController extends Controller
{
    #[OA\Get(
        path: "/api/potentials",
        summary: "Daftar Potensi",
        tags: ["Potentials"],
        responses: [
            new OA\Response(response: 200, description: "Berhasil")
        ]
    )]
    public function index()
    {
        $potentials = Potential::with('user')->orderBy('trans_date', 'desc')->get();
        return response()->json($potentials);
    }

    #[OA\Post(
        path: "/api/potentials",
        summary: "Tambah Potensi",
        tags: ["Potentials"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["category", "description", "amount", "trans_date", "status"],
                properties: [
                    new OA\Property(property: "user_id", type: "integer", example: 1),
                    new OA\Property(property: "category", type: "string", example: "Proyek A"),
                    new OA\Property(property: "description", type: "string", example: "Potensi pendapatan bulan depan"),
                    new OA\Property(property: "amount", type: "number", example: 5000000),
                    new OA\Property(property: "trans_date", type: "string", format: "date", example: "2026-07-27"),
                    new OA\Property(property: "status", type: "string", example: "pending")
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
            'description' => 'required|string',
            'amount' => 'required|numeric',
            'trans_date' => 'required|date',
            'status' => 'required|string|max:255'
        ]);

        $potential = Potential::create($validated);
        $potential->load('user');
        return response()->json($potential, 201);
    }

    #[OA\Get(
        path: "/api/potentials/{id}",
        summary: "Detail Potensi",
        tags: ["Potentials"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Berhasil")
        ]
    )]
    public function show(Potential $potential)
    {
        $potential->load('user');
        return response()->json($potential);
    }

    #[OA\Put(
        path: "/api/potentials/{id}",
        summary: "Update Potensi",
        tags: ["Potentials"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "user_id", type: "integer"),
                    new OA\Property(property: "category", type: "string"),
                    new OA\Property(property: "description", type: "string"),
                    new OA\Property(property: "amount", type: "number"),
                    new OA\Property(property: "trans_date", type: "string", format: "date"),
                    new OA\Property(property: "status", type: "string")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Berhasil diupdate")
        ]
    )]
    public function update(Request $request, Potential $potential)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'category' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'amount' => 'sometimes|required|numeric',
            'trans_date' => 'sometimes|required|date',
            'status' => 'sometimes|required|string|max:255'
        ]);

        $potential->update($validated);
        $potential->load('user');
        return response()->json($potential);
    }

    #[OA\Delete(
        path: "/api/potentials/{id}",
        summary: "Hapus Potensi",
        tags: ["Potentials"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Berhasil dihapus")
        ]
    )]
    public function destroy(Potential $potential)
    {
        $potential->delete();
        return response()->noContent();
    }
}
