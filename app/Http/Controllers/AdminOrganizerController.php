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
     *     summary="Admin: Get all organizers",
     *     tags={"Admin Organizers"},
     *     @OA\Response(
     *         response=200,
     *         description="List of organizers",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Organizer"))
     *         )
     *     )
     * )
     */
    public function index()
    {
        $organizers = Organizer::latest()->get();
        return response()->json(['data' => $organizers]);
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
     * @OA\Post(
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
