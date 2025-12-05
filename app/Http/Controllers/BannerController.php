<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BannerController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/banners",
     *     tags={"Banners"},
     *     summary="Get active banners for mobile app (USER)",
     *     @OA\Response(
     *         response=200,
     *         description="List of active banners",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="10% cashback on your first order"),
     *                     @OA\Property(property="description", type="string", example="Get 10% back"),
     *                     @OA\Property(property="image", type="string", example="https://dabapp.com/storage/banners/banner1.jpg"),
     *                     @OA\Property(property="link", type="string", example="/promotions/first-order"),
     *                     @OA\Property(property="order", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        // Pour les USERS: Seulement les banners actifs avec dates valides
        $banners = Banner::where('is_active', true)
            ->where(function($query) {
                $query->whereNull('start_date')
                      ->orWhere('start_date', '<=', now());
            })
            ->where(function($query) {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', now());
            })
            ->orderBy('order')
            ->get()
            ->map(function($banner) {
                return [
                    'id' => $banner->id,
                    'title' => $banner->title,
                    'description' => $banner->description,
                    'image' => $banner->image,
                    'link' => $banner->link,
                    'order' => $banner->order
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $banners
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/banners",
     *     tags={"Banners Management"},
     *     summary="Get all banners (ADMIN - tous les banners)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of all banners"
     *     )
     * )
     */
    public function adminIndex()
    {
        // Pour les ADMINS: TOUS les banners sans filtre
        $banners = Banner::orderBy('order')->get()->map(function($banner) {
            return [
                'id' => $banner->id,
                'title' => $banner->title,
                'description' => $banner->description,
                'image' => $banner->image,
                'link' => $banner->link,
                'order' => $banner->order,
                'is_active' => $banner->is_active,
                'start_date' => $banner->start_date?->format('Y-m-d'),
                'end_date' => $banner->end_date?->format('Y-m-d'),
                'created_at' => $banner->created_at->format('Y-m-d H:i:s')
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $banners
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/banners",
     *     tags={"Banners Management"},
     *     summary="Create new banner",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "image"},
     *             @OA\Property(property="title", type="string", example="10% cashback"),
     *             @OA\Property(property="description", type="string", example="Get 10% back on first order"),
     *             @OA\Property(property="image", type="string", example="https://dabapp.com/storage/uploads/banner123.jpg"),
     *             @OA\Property(property="link", type="string", example="/promotions/cashback"),
     *             @OA\Property(property="order", type="integer", example=1),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="start_date", type="string", format="date", example="2024-01-01"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2024-12-31")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Banner created successfully"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'required|string',
            'link' => 'nullable|string|max:255',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $banner = Banner::create([
            'title' => $request->title,
            'description' => $request->description,
            'image' => $request->image,
            'link' => $request->link,
            'order' => $request->order ?? 0,
            'is_active' => $request->is_active ?? true,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Banner created successfully',
            'data' => [
                'id' => $banner->id,
                'title' => $banner->title,
                'image' => $banner->image,
                'is_active' => $banner->is_active
            ]
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/banners/{id}",
     *     tags={"Banners Management"},
     *     summary="Get single banner",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Banner details"
     *     )
     * )
     */
    public function show($id)
    {
        $banner = Banner::find($id);

        if (!$banner) {
            return response()->json([
                'success' => false,
                'message' => 'Banner not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $banner->id,
                'title' => $banner->title,
                'description' => $banner->description,
                'image' => $banner->image,
                'link' => $banner->link,
                'order' => $banner->order,
                'is_active' => $banner->is_active,
                'start_date' => $banner->start_date?->format('Y-m-d'),
                'end_date' => $banner->end_date?->format('Y-m-d'),
                'created_at' => $banner->created_at->format('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/banners/{id}",
     *     tags={"Banners Management"},
     *     summary="Update banner",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="image", type="string", example="https://dabapp.com/storage/uploads/new-banner.jpg"),
     *             @OA\Property(property="link", type="string"),
     *             @OA\Property(property="order", type="integer"),
     *             @OA\Property(property="is_active", type="boolean"),
     *             @OA\Property(property="start_date", type="string", format="date"),
     *             @OA\Property(property="end_date", type="string", format="date")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Banner updated successfully"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $banner = Banner::find($id);

        if (!$banner) {
            return response()->json([
                'success' => false,
                'message' => 'Banner not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'link' => 'nullable|string|max:255',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $banner->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Banner updated successfully',
            'data' => [
                'id' => $banner->id,
                'title' => $banner->title,
                'image' => $banner->image,
                'is_active' => $banner->is_active
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/banners/{id}",
     *     tags={"Banners Management"},
     *     summary="Delete banner",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Banner deleted successfully"
     *     )
     * )
     */
    public function destroy($id)
    {
        $banner = Banner::find($id);

        if (!$banner) {
            return response()->json([
                'success' => false,
                'message' => 'Banner not found'
            ], 404);
        }

        $banner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Banner deleted successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/banners/{id}/toggle",
     *     tags={"Banners Management"},
     *     summary="Toggle banner active status",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Banner status toggled"
     *     )
     * )
     */
    public function toggleStatus($id)
    {
        $banner = Banner::find($id);

        if (!$banner) {
            return response()->json([
                'success' => false,
                'message' => 'Banner not found'
            ], 404);
        }

        $banner->is_active = !$banner->is_active;
        $banner->save();

        return response()->json([
            'success' => true,
            'message' => 'Banner status updated',
            'data' => [
                'is_active' => $banner->is_active
            ]
        ]);
    }
}
