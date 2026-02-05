<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EventSponsor;
use Illuminate\Http\Request;

class AdminEventSponsorController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/event-sponsors",
     *     summary="Admin: Get all sponsors",
     *     tags={"Admin Event Sponsors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of sponsors",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EventSponsor"))
     *         )
     *     )
     * )
     */
    public function index()
    {
        $sponsors = EventSponsor::all();
        return response()->json(['data' => $sponsors]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/event-sponsors",
     *     summary="Admin: Create a new sponsor",
     *     tags={"Admin Event Sponsors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Tech Corp"),
     *             @OA\Property(property="logo", type="string", format="url", example="https://example.com/logo.png"),
     *             @OA\Property(property="website", type="string", format="url", example="https://example.com"),
     *             @OA\Property(property="description", type="string", example="Global Tech Leader")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Sponsor created")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|url',
            'website' => 'nullable|url',
            'description' => 'nullable|string',
        ]);

        $sponsor = EventSponsor::create($validated);

        return response()->json(['message' => 'Sponsor created', 'data' => $sponsor], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/event-sponsors/{id}",
     *     summary="Admin: Update a sponsor",
     *     tags={"Admin Event Sponsors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="logo", type="string", format="url"),
     *             @OA\Property(property="website", type="string", format="url"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Sponsor updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $sponsor = EventSponsor::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'logo' => 'nullable|url',
            'website' => 'nullable|url',
            'description' => 'nullable|string',
        ]);

        $sponsor->update($validated);

        return response()->json(['message' => 'Sponsor updated', 'data' => $sponsor]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/event-sponsors/{id}",
     *     summary="Admin: Delete a sponsor",
     *     tags={"Admin Event Sponsors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Sponsor deleted")
     * )
     */
    public function destroy($id)
    {
        $sponsor = EventSponsor::findOrFail($id);

        // Optional: Check if used in any event
        if ($sponsor->events()->exists()) {
            return response()->json(['message' => 'Cannot delete sponsor linked to events'], 400);
        }

        $sponsor->delete();

        return response()->json(['message' => 'Sponsor deleted']);
    }
}
