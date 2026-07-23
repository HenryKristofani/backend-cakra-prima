<?php

namespace App\Http\Controllers;

use App\Models\DebtGroup;
use App\Models\DebtItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class DebtItemController extends Controller
{
    #[OA\Post(
        path: "/api/debt-groups/{debtGroup}/items",
        summary: "Tambah Item Hutang",
        tags: ["Debt Items"],
        parameters: [
            new OA\Parameter(name: "debtGroup", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["description", "amount"],
                properties: [
                    new OA\Property(property: "no", type: "integer", example: 1),
                    new OA\Property(property: "description", type: "string", example: "Beli material"),
                    new OA\Property(property: "trans_date", type: "string", format: "date", example: "2026-07-23"),
                    new OA\Property(property: "amount", type: "number", example: 150000)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Berhasil ditambahkan",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Item hutang berhasil ditambahkan."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function store(Request $request, DebtGroup $debtGroup): JsonResponse
    {
        $validated = $request->validate([
            'no' => ['nullable', 'integer', 'min:0'],
            'description' => ['required', 'string', 'max:255'],
            'trans_date' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $item = $debtGroup->items()->create($validated);
        $debtGroup->recalculate();

        return response()->json([
            'message' => 'Item hutang berhasil ditambahkan.',
            'data' => $item
        ], 201);
    }

    #[OA\Post(
        path: "/api/debt-groups/{debtGroup}/items/bulk",
        summary: "Bulk Insert Item Hutang",
        tags: ["Debt Items"],
        parameters: [
            new OA\Parameter(name: "debtGroup", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["items"],
                properties: [
                    new OA\Property(
                        property: "items",
                        type: "array",
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: "no", type: "integer", example: 1),
                                new OA\Property(property: "description", type: "string", example: "Beli material"),
                                new OA\Property(property: "trans_date", type: "string", format: "date", example: "2026-07-23"),
                                new OA\Property(property: "amount", type: "number", example: 150000)
                            ]
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Berhasil ditambahkan",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "3 item berhasil ditambahkan."),
                        new OA\Property(property: "count", type: "integer", example: 3)
                    ]
                )
            )
        ]
    )]
    public function bulkStore(Request $request, DebtGroup $debtGroup): JsonResponse
    {
        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.no' => ['nullable', 'integer', 'min:0'],
            'items.*.trans_date' => ['nullable', 'date'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($request, $debtGroup) {
            foreach ($request->items as $itemData) {
                $debtGroup->items()->create([
                    'no' => $itemData['no'] ?? null,
                    'description' => $itemData['description'],
                    'trans_date' => $itemData['trans_date'] ?? null,
                    'amount' => $itemData['amount'],
                ]);
            }
            $debtGroup->recalculate();
        });

        return response()->json([
            'message' => count($request->items) . ' item berhasil ditambahkan.',
            'count' => count($request->items),
        ], 201);
    }

    #[OA\Put(
        path: "/api/debt-groups/{debtGroup}/items/bulk",
        summary: "Bulk Update Item Hutang",
        tags: ["Debt Items"],
        parameters: [
            new OA\Parameter(name: "debtGroup", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["items"],
                properties: [
                    new OA\Property(
                        property: "items",
                        type: "array",
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "no", type: "integer", example: 1),
                                new OA\Property(property: "description", type: "string", example: "Beli material updated"),
                                new OA\Property(property: "trans_date", type: "string", format: "date", example: "2026-07-23"),
                                new OA\Property(property: "amount", type: "number", example: 150000)
                            ]
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Berhasil diperbarui",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "3 item berhasil diperbarui."),
                        new OA\Property(property: "count", type: "integer", example: 3)
                    ]
                )
            )
        ]
    )]
    public function bulkUpdate(Request $request, DebtGroup $debtGroup): JsonResponse
    {
        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:debt_items,id'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.no' => ['nullable', 'integer', 'min:0'],
            'items.*.trans_date' => ['nullable', 'date'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($request, $debtGroup) {
            foreach ($request->items as $itemData) {
                DebtItem::where('id', $itemData['id'])
                    ->where('debt_group_id', $debtGroup->id)
                    ->update([
                        'no' => $itemData['no'] ?? null,
                        'description' => $itemData['description'],
                        'trans_date' => $itemData['trans_date'] ?? null,
                        'amount' => $itemData['amount'],
                    ]);
            }
            $debtGroup->recalculate();
        });

        return response()->json([
            'message' => count($request->items) . ' item berhasil diperbarui.',
            'count' => count($request->items),
        ]);
    }

    #[OA\Put(
        path: "/api/debt-groups/{debtGroup}/items/{debtItem}",
        summary: "Update Item Hutang",
        tags: ["Debt Items"],
        parameters: [
            new OA\Parameter(name: "debtGroup", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "debtItem", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["description", "amount"],
                properties: [
                    new OA\Property(property: "no", type: "integer", example: 1),
                    new OA\Property(property: "description", type: "string", example: "Beli material update"),
                    new OA\Property(property: "trans_date", type: "string", format: "date", example: "2026-07-23"),
                    new OA\Property(property: "amount", type: "number", example: 200000)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Berhasil diperbarui",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Item hutang berhasil diperbarui."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function update(Request $request, DebtGroup $debtGroup, DebtItem $debtItem): JsonResponse
    {
        $validated = $request->validate([
            'no' => ['nullable', 'integer', 'min:0'],
            'description' => ['required', 'string', 'max:255'],
            'trans_date' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $debtItem->update($validated);
        $debtGroup->recalculate();

        return response()->json([
            'message' => 'Item hutang berhasil diperbarui.',
            'data' => $debtItem
        ]);
    }

    #[OA\Delete(
        path: "/api/debt-groups/{debtGroup}/items/{debtItem}",
        summary: "Hapus Item Hutang",
        tags: ["Debt Items"],
        parameters: [
            new OA\Parameter(name: "debtGroup", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "debtItem", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Berhasil dihapus",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Item hutang berhasil dihapus.")
                    ]
                )
            )
        ]
    )]
    public function destroy(DebtGroup $debtGroup, DebtItem $debtItem): JsonResponse
    {
        $debtItem->delete();
        $debtGroup->recalculate();

        return response()->json([
            'message' => 'Item hutang berhasil dihapus.'
        ]);
    }
}