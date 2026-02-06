<?php

namespace App\Http\Controllers;

use App\Models\Organizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminOrganizerController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/organizers",
     *     summary="Admin: Get all organizers with pagination and search",
     *     tags={"Admin Organizers"},
     *     @OA\Parameter(name="page", in="query", description="Page number", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page (leave empty for all)", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", description="Search by name or description", required=false, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="List of organizers",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(type="array", @OA\Items(ref="#/components/schemas/Organizer")),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Organizer")),
     *                     @OA\Property(property="links", type="object"),
     *                     @OA\Property(property="meta", type="object")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Organizer::latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('per_page')) {
            $organizers = $query->paginate((int) $request->per_page);
        } else {
            $organizers = $query->get();
        }

        return response()->json($organizers);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/organizers",
     *     summary="Admin: Create a new organizer",
     *     tags={"Admin Organizers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"name"},
     *                 @OA\Property(property="name", type="string", example="Super Event Organizers LLC"),
     *                 @OA\Property(property="description", type="string", example="Leading events company"),
     *                 @OA\Property(property="logo", type="string", description="URL of the logo", example="https://example.com/logo.png")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Organizer created successfully")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|string',
        ]);

        $organizer = Organizer::create($validated);

        return response()->json(['message' => 'Organizer created successfully', 'data' => $organizer], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/organizers/{id}",
     *     summary="Admin: Get organizer details",
     *     tags={"Admin Organizers"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Organizer details")
     * )
     */
    public function show($id)
    {
        $organizer = Organizer::findOrFail($id);
        return response()->json(['data' => $organizer]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/organizers/{id}",
     *     summary="Admin: Update an organizer",
     *     tags={"Admin Organizers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string", example="Super Event Organizers LLC"),
     *                 @OA\Property(property="description", type="string", example="Leading events company"),
     *                 @OA\Property(property="logo", type="string", description="URL of the logo", example="https://example.com/logo.png")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organizer updated successfully"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $organizer = Organizer::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|string',
        ]);

        $organizer->update($validated);

        return response()->json(['message' => 'Organizer updated successfully', 'data' => $organizer]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/organizers/{id}",
     *     summary="Admin: Delete an organizer",
     *     tags={"Admin Organizers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Organizer deleted successfully")
     * )
     */
    public function destroy($id)
    {
        $organizer = Organizer::findOrFail($id);
        $organizer->delete();

        return response()->json(['message' => 'Organizer deleted successfully']);
    }
}
