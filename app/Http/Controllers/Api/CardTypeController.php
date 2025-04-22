<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CardType;
use Illuminate\Http\Request;

class CardTypeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/card-types",
     *     summary="Get all card types",
     *     tags={"Card Types"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="List of card types")
     * )
     */
    public function index()
    {
        return response()->json(CardType::all());
    }

    /**
     * @OA\Post(
     *     path="/api/card-types",
     *     summary="Create a new card type",
     *     tags={"Card Types"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Gold")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Card type created")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $cardType = CardType::create($validated);
        return response()->json($cardType, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/card-types/{id}",
     *     summary="Get card type by ID",
     *     tags={"Card Types"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Card type details"),
     *     @OA\Response(response=404, description="Card type not found")
     * )
     */
    public function show($id)
    {
        return response()->json(CardType::findOrFail($id));
    }

    /**
     * @OA\Put(
     *     path="/api/card-types/{id}",
     *     summary="Update card type",
     *     tags={"Card Types"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Silver")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Card type updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $cardType = CardType::findOrFail($id);
        $cardType->update($request->only('name'));
        return response()->json($cardType);
    }

    /**
     * @OA\Delete(
     *     path="/api/card-types/{id}",
     *     summary="Delete card type",
     *     tags={"Card Types"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Card type deleted")
     * )
     */
    public function destroy($id)
    {
        CardType::findOrFail($id)->delete();
        return response()->json(['message' => 'Card type deleted']);
    }
}
