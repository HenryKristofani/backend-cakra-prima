<?php

namespace App\Http\Controllers;

use App\Models\DebtGroup;
use App\Exports\DebtGroupExport;
use App\Imports\DebtGroupImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use OpenApi\Attributes as OA;

class DebtGroupController extends Controller
{
    #[OA\Get(
        path: "/api/debt-groups",
        summary: "Daftar Debt Group",
        tags: ["Debt Groups"],
        responses: [
            new OA\Response(
                response: 200, 
                description: "Daftar Debt Group",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data", 
                            type: "array", 
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "name", type: "string", example: "Hutang Proyek A"),
                                    new OA\Property(property: "total_amount", type: "number", example: 1500000),
                                    new OA\Property(property: "remaining_amount", type: "number", example: 1000000),
                                    new OA\Property(property: "items_count", type: "integer", example: 3),
                                    new OA\Property(property: "payments_count", type: "integer", example: 1)
                                ]
                            )
                        )
                    ]
                )
            )
        ]
    )]
    public function index(): JsonResponse
    {
        $debtGroups = DebtGroup::withCount(['items', 'payments'])
            ->latest()
            ->paginate(15);

        return response()->json($debtGroups);
    }

    #[OA\Post(
        path: "/api/debt-groups",
        summary: "Tambah Debt Group",
        tags: ["Debt Groups"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Hutang Proyek A")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201, 
                description: "Berhasil ditambahkan",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Grup hutang berhasil ditambahkan."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $debtGroup = DebtGroup::create([
            'name' => $validated['name'],
            'total_amount' => 0,
            'remaining_amount' => 0,
        ]);

        return response()->json([
            'message' => 'Grup hutang berhasil ditambahkan.',
            'data' => $debtGroup
        ], 201);
    }

    #[OA\Get(
        path: "/api/debt-groups/{id}",
        summary: "Detail Debt Group",
        tags: ["Debt Groups"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200, 
                description: "Detail Debt Group",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "id", type: "integer", example: 1),
                        new OA\Property(property: "name", type: "string", example: "Hutang Proyek A"),
                        new OA\Property(property: "total_amount", type: "number", example: 1500000),
                        new OA\Property(property: "remaining_amount", type: "number", example: 1000000),
                        new OA\Property(property: "items", type: "array", items: new OA\Items(
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "no", type: "integer", example: 1),
                                new OA\Property(property: "description", type: "string", example: "Beli material"),
                                new OA\Property(property: "trans_date", type: "string", format: "date", example: "2026-07-23"),
                                new OA\Property(property: "amount", type: "number", example: 1500000)
                            ]
                        )),
                        new OA\Property(property: "payments", type: "array", items: new OA\Items(
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "description", type: "string", example: "Cicilan 1"),
                                new OA\Property(property: "payment_date", type: "string", format: "date", example: "2026-07-24"),
                                new OA\Property(property: "amount", type: "number", example: 500000)
                            ]
                        ))
                    ]
                )
            )
        ]
    )]
    public function show(DebtGroup $debtGroup): JsonResponse
    {
        $debtGroup->load(['items' => fn ($q) => $q->orderBy('no'), 'payments']);

        return response()->json($debtGroup);
    }

    #[OA\Put(
        path: "/api/debt-groups/{id}",
        summary: "Update Debt Group",
        tags: ["Debt Groups"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Hutang Proyek A Update")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200, 
                description: "Berhasil diperbarui",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Grup hutang berhasil diperbarui."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function update(Request $request, DebtGroup $debtGroup): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $debtGroup->update($validated);

        return response()->json([
            'message' => 'Grup hutang berhasil diperbarui.',
            'data' => $debtGroup
        ]);
    }

    #[OA\Delete(
        path: "/api/debt-groups/{id}",
        summary: "Hapus Debt Group",
        tags: ["Debt Groups"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200, 
                description: "Berhasil dihapus",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Grup hutang berhasil dihapus.")
                    ]
                )
            )
        ]
    )]
    public function destroy(DebtGroup $debtGroup): JsonResponse
    {
        $debtGroup->delete();

        return response()->json([
            'message' => 'Grup hutang berhasil dihapus.'
        ]);
    }

    #[OA\Get(
        path: "/api/debt-groups/{id}/export",
        summary: "Export Debt Group ke Excel",
        tags: ["Debt Groups"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "File Excel (.xlsx)")
        ]
    )]
    public function export(DebtGroup $debtGroup)
    {
        $debtGroup->load(['items' => fn ($q) => $q->orderBy('no'), 'payments']);

        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $debtGroup->name) . '.xlsx';

        return Excel::download(new DebtGroupExport($debtGroup), $filename);
    }

    #[OA\Post(
        path: "/api/debt-groups/{id}/import",
        summary: "Import Debt Group dari CSV/Excel",
        tags: ["Debt Groups"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["file"],
                    properties: [
                        new OA\Property(property: "file", type: "string", format: "binary")
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Berhasil diimport")
        ]
    )]
    public function import(Request $request, DebtGroup $debtGroup): JsonResponse
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xlsx,xls|max:5120',
        ]);

        Excel::import(new DebtGroupImport($debtGroup), $request->file('file'));

        return response()->json([
            'message' => 'Data berhasil diimport.'
        ]);
    }
}