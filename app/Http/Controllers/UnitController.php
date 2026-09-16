<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $query = Unit::query();

        if ($request->has('search') && !empty($request->search)) {
            $search = strtolower($request->search);
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(symbol) LIKE ?', ["%{$search}%"]);
        }

        if ($request->has('is_active')) {
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        $units = $query->orderBy('name', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $units
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:units,name',
            'symbol' => 'required|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $unit = Unit::create([
            'name' => $validated['name'],
            'symbol' => $validated['symbol'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Satuan berhasil ditambahkan',
            'data' => $unit
        ], 201);
    }

    public function update(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:units,name,' . $unit->id,
            'symbol' => 'required|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $unit->update([
            'name' => $validated['name'],
            'symbol' => $validated['symbol'],
            'is_active' => $validated['is_active'] ?? $unit->is_active,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Satuan berhasil diperbarui',
            'data' => $unit
        ]);
    }
}
