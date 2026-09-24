<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFundMovementRequest;
use App\Services\FundMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FundMovementController extends Controller
{
    protected FundMovementService $fundMovementService;

    public function __construct(FundMovementService $fundMovementService)
    {
        $this->fundMovementService = $fundMovementService;
    }

    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        $query = \App\Models\FundMovement::with([
            'fundSource:id,name', 
            'sourceProject:id,name', 
            'destinationProject:id,name'
        ])->orderByDesc('id');

        if ($request->has('fund_source_id')) {
            $query->where('fund_source_id', $request->input('fund_source_id'));
        }

        if ($request->has('project_id')) {
            $projectId = $request->input('project_id');
            $query->where(function ($q) use ($projectId) {
                $q->where('source_project_id', $projectId)
                  ->orWhere('destination_project_id', $projectId);
            });
        }

        return response()->json($query->get());
    }

    public function store(StoreFundMovementRequest $request): JsonResponse
    {
        $data = $request->validated();
        $type = $data['type'] ?? \App\Enums\FundMovementType::InitialAllocation->value;
        $userId = Auth::id() ?? 1; // Fallback for manual testing

        try {
            if ($type === \App\Enums\FundMovementType::InitialAllocation->value) {
                $movement = $this->fundMovementService->createInitialAllocation($data, $userId);
                $message = 'Alokasi modal berhasil ditambahkan.';
            } elseif ($type === \App\Enums\FundMovementType::Transfer->value) {
                $movement = $this->fundMovementService->createTransfer($data, $userId);
                $message = 'Realokasi modal berhasil dilakukan.';
            } else {
                $movement = $this->fundMovementService->createWithdrawal($data, $userId);
                $message = 'Penarikan modal kembali ke sumber berhasil dilakukan.';
            }
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $message,
            'data'    => $movement,
        ], 201);
    }

    public function reverse(\App\Models\FundMovement $fundMovement): JsonResponse
    {
        $userId = Auth::id() ?? 1;

        try {
            $reversal = $this->fundMovementService->reverseMovement($fundMovement, $userId);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Movement berhasil dibatalkan.',
            'data'    => $reversal->load(['fundSource', 'sourceProject', 'destinationProject']),
        ], 201);
    }
}
