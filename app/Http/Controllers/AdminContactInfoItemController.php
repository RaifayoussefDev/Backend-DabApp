<?php

namespace App\Http\Controllers;

use App\Models\ContactInfoItem;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Admin Contact Info Items",
 *     description="Extra, admin-managed contact channels shown on the public Contact Us page alongside the 4 fixed fields"
 * )
 */
class AdminContactInfoItemController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/contact-info-items",
     *     summary="List extra contact info items (Admin)",
     *     tags={"Admin Contact Info Items"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Items retrieved, in display order")
     * )
     */
    public function index()
    {
        return response()->json(ContactInfoItem::ordered()->get());
    }

    /**
     * @OA\Post(
     *     path="/api/admin/contact-info-items",
     *     summary="Add an extra contact info item",
     *     tags={"Admin Contact Info Items"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"label_en", "value_en"},
     *             @OA\Property(property="icon", type="string", nullable=true, example="whatsapp"),
     *             @OA\Property(property="label_en", type="string", example="WhatsApp"),
     *             @OA\Property(property="label_ar", type="string", nullable=true, example="واتساب"),
     *             @OA\Property(property="value_en", type="string", example="+966 50 123 4567"),
     *             @OA\Property(property="value_ar", type="string", nullable=true),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="order", type="integer", example=0)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Item created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'icon' => 'nullable|string|max:100',
            'label_en' => 'required|string|max:255',
            'label_ar' => 'nullable|string|max:255',
            'value_en' => 'required|string|max:255',
            'value_ar' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'order' => 'nullable|integer',
        ]);

        $item = ContactInfoItem::create($validated);

        return response()->json($item, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/contact-info-items/{id}",
     *     summary="Update an extra contact info item",
     *     tags={"Admin Contact Info Items"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Item updated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $item = ContactInfoItem::findOrFail($id);

        $validated = $request->validate([
            'icon' => 'nullable|string|max:100',
            'label_en' => 'sometimes|required|string|max:255',
            'label_ar' => 'nullable|string|max:255',
            'value_en' => 'sometimes|required|string|max:255',
            'value_ar' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'order' => 'nullable|integer',
        ]);

        $item->update($validated);

        return response()->json($item);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/contact-info-items/{id}/toggle",
     *     summary="Toggle whether an item is shown on the public page",
     *     tags={"Admin Contact Info Items"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Item toggled")
     * )
     */
    public function toggleStatus($id)
    {
        $item = ContactInfoItem::findOrFail($id);
        $item->is_active = !$item->is_active;
        $item->save();

        return response()->json([
            'success' => true,
            'data' => $item,
            'message' => 'Item ' . ($item->is_active ? 'shown' : 'hidden') . ' successfully',
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/contact-info-items/{id}",
     *     summary="Delete an extra contact info item",
     *     tags={"Admin Contact Info Items"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Item deleted")
     * )
     */
    public function destroy($id)
    {
        $item = ContactInfoItem::findOrFail($id);
        $item->delete();

        return response()->json(['success' => true, 'message' => 'Item deleted successfully']);
    }
}
