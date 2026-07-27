<?php

namespace App\Http\Controllers;

use App\Models\BudgetNeed;
use App\Models\CashFlowPeriod;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class BudgetNeedController extends Controller
{
    #[OA\Post(
        path: "/api/cash-flow-periods/{period}/budget-needs",
        summary: "Tambah Kebutuhan Anggaran",
        tags: ["Budget Needs"],
        parameters: [
            new OA\Parameter(name: "period", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["description", "amount"],
                properties: [
                    new OA\Property(property: "description", type: "string", example: "Tagihan Listrik"),
                    new OA\Property(property: "amount", type: "number", example: 500000)
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
            'description' => 'required|string',
            'amount' => 'required|numeric'
        ]);

        $budgetNeed = $cashFlowPeriod->budgetNeeds()->create($validated);
        return response()->json($budgetNeed, 201);
    }

    #[OA\Put(
        path: "/api/cash-flow-periods/{period}/budget-needs/{budgetNeed}",
        summary: "Update Kebutuhan Anggaran",
        tags: ["Budget Needs"],
        parameters: [
            new OA\Parameter(name: "period", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "budgetNeed", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "description", type: "string"),
                    new OA\Property(property: "amount", type: "number")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Berhasil diupdate")
        ]
    )]
    public function update(Request $request, CashFlowPeriod $cashFlowPeriod, BudgetNeed $budgetNeed)
    {
        $validated = $request->validate([
            'description' => 'sometimes|required|string',
            'amount' => 'sometimes|required|numeric'
        ]);

        $budgetNeed->update($validated);
        return response()->json($budgetNeed);
    }

    #[OA\Delete(
        path: "/api/cash-flow-periods/{period}/budget-needs/{budgetNeed}",
        summary: "Hapus Kebutuhan Anggaran",
        tags: ["Budget Needs"],
        parameters: [
            new OA\Parameter(name: "period", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "budgetNeed", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Berhasil dihapus")
        ]
    )]
    public function destroy(CashFlowPeriod $cashFlowPeriod, BudgetNeed $budgetNeed)
    {
        $budgetNeed->delete();
        return response()->noContent();
    }
}
