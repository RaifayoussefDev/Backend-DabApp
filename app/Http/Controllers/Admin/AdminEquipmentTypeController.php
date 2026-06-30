<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EquipmentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminEquipmentTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $types = EquipmentType::orderBy('sort_order')->orderBy('name')->get();

        return response()->json(['success' => true, 'data' => $types]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100|unique:equipment_types,name',
            'name_ar'    => 'nullable|string|max:100',
            'icon'       => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'nullable|boolean',
        ]);

        $type = EquipmentType::create([
            'name'       => $validated['name'],
            'name_ar'    => $validated['name_ar'] ?? null,
            'icon'       => $validated['icon'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active'  => $validated['is_active'] ?? true,
        ]);

        return response()->json(['success' => true, 'data' => $type, 'message' => 'Equipment type created.'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $type = EquipmentType::findOrFail($id);

        $validated = $request->validate([
            'name'       => 'sometimes|string|max:100|unique:equipment_types,name,' . $id,
            'name_ar'    => 'nullable|string|max:100',
            'icon'       => 'nullable|string|max:100',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active'  => 'sometimes|boolean',
        ]);

        $type->update($validated);

        return response()->json(['success' => true, 'data' => $type, 'message' => 'Equipment type updated.']);
    }

    public function destroy(int $id): JsonResponse
    {
        $type = EquipmentType::findOrFail($id);
        $type->delete();

        return response()->json(['success' => true, 'message' => 'Equipment type deleted.']);
    }
}
