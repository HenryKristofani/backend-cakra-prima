<?php

namespace App\Http\Controllers;

use App\Models\CashFlowPeriod;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CashFlowPeriodController extends Controller
{
    #[OA\Get(
        path: "/api/cash-flow-periods",
        summary: "Daftar Periode Arus Kas",
        tags: ["Cash Flow Periods"],
        responses: [
            new OA\Response(response: 200, description: "Berhasil")
        ]
    )]
    public function index()
    {
        $periods = CashFlowPeriod::orderBy('start_date', 'desc')->get();
        return response()->json($periods);
    }

    #[OA\Post(
        path: "/api/cash-flow-periods",
        summary: "Tambah Periode Arus Kas",
        tags: ["Cash Flow Periods"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["period_label"],
                properties: [
                    new OA\Property(property: "period_label", type: "string", example: "Juli 2026"),
                    new OA\Property(property: "start_date", type: "string", format: "date", example: "2026-07-01"),
                    new OA\Property(property: "end_date", type: "string", format: "date", example: "2026-07-31"),
                    new OA\Property(property: "classification", type: "string", example: "SEMUA")
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
            'period_label' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'classification' => 'nullable|string|max:255'
        ]);

        if (!isset($validated['classification'])) {
            $validated['classification'] = 'SEMUA';
        }

        $period = CashFlowPeriod::create($validated);
        return response()->json($period, 201);
    }

    #[OA\Get(
        path: "/api/cash-flow-periods/{id}",
        summary: "Detail Periode Arus Kas",
        tags: ["Cash Flow Periods"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Berhasil")
        ]
    )]
    public function show(CashFlowPeriod $cashFlowPeriod)
    {
        $cashFlowPeriod->load(['items', 'transactions', 'budgetNeeds']);
        return response()->json($cashFlowPeriod);
    }

    #[OA\Put(
        path: "/api/cash-flow-periods/{id}",
        summary: "Update Periode Arus Kas",
        tags: ["Cash Flow Periods"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "period_label", type: "string"),
                    new OA\Property(property: "start_date", type: "string", format: "date"),
                    new OA\Property(property: "end_date", type: "string", format: "date"),
                    new OA\Property(property: "classification", type: "string")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Berhasil diupdate")
        ]
    )]
    public function update(Request $request, CashFlowPeriod $cashFlowPeriod)
    {
        $validated = $request->validate([
            'period_label' => 'sometimes|required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'classification' => 'nullable|string|max:255'
        ]);

        $cashFlowPeriod->update($validated);
        return response()->json($cashFlowPeriod);
    }

    #[OA\Delete(
        path: "/api/cash-flow-periods/{id}",
        summary: "Hapus Periode Arus Kas",
        tags: ["Cash Flow Periods"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Berhasil dihapus")
        ]
    )]
    public function destroy(CashFlowPeriod $cashFlowPeriod)
    {
        $cashFlowPeriod->delete();
        return response()->noContent();
    }
}
