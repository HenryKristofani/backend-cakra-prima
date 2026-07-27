<?php

namespace App\Http\Controllers;

use App\Models\CashFlowPeriod;
use App\Models\OperationalCashFlowItem;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class OperationalCashFlowItemController extends Controller
{
    #[OA\Post(
        path: "/api/cash-flow-periods/{period}/items",
        summary: "Tambah Item Arus Kas Operasional",
        tags: ["Cash Flow Items"],
        parameters: [
            new OA\Parameter(name: "period", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["section", "label", "amount"],
                properties: [
                    new OA\Property(property: "section", type: "string", example: "modal_awal"),
                    new OA\Property(property: "code", type: "string", example: "A"),
                    new OA\Property(property: "label", type: "string", example: "Modal Awal"),
                    new OA\Property(property: "amount", type: "number", example: 10000000)
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
            'section' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
            'label' => 'required|string|max:255',
            'amount' => 'required|numeric'
        ]);

        $item = $cashFlowPeriod->items()->create($validated);
        return response()->json($item, 201);
    }

    #[OA\Put(
        path: "/api/cash-flow-periods/{period}/items/{item}",
        summary: "Update Item Arus Kas Operasional",
        tags: ["Cash Flow Items"],
        parameters: [
            new OA\Parameter(name: "period", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "item", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "section", type: "string"),
                    new OA\Property(property: "code", type: "string"),
                    new OA\Property(property: "label", type: "string"),
                    new OA\Property(property: "amount", type: "number")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Berhasil diupdate")
        ]
    )]
    public function update(Request $request, CashFlowPeriod $cashFlowPeriod, OperationalCashFlowItem $item)
    {
        $validated = $request->validate([
            'section' => 'sometimes|required|string|max:255',
            'code' => 'nullable|string|max:255',
            'label' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric'
        ]);

        $item->update($validated);
        return response()->json($item);
    }

    #[OA\Delete(
        path: "/api/cash-flow-periods/{period}/items/{item}",
        summary: "Hapus Item Arus Kas Operasional",
        tags: ["Cash Flow Items"],
        parameters: [
            new OA\Parameter(name: "period", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "item", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Berhasil dihapus")
        ]
    )]
    public function destroy(CashFlowPeriod $cashFlowPeriod, OperationalCashFlowItem $item)
    {
        $item->delete();
        return response()->noContent();
    }
}
