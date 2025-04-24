<?php

namespace App\Http\Controllers;

use App\Models\Color;
use Illuminate\Http\Request;

class ColorController extends Controller
{

   /**
     * @OA\Get(
     *     path="/api/colors",
     *     summary="Get all colors",
     *     tags={"Colors"},
     *     @OA\Response(
     *         response=200,
     *         description="List of colors",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Red")
     *             )
     *         )
     *     )
     * )
     */


    public function index()
    {
        $colors = Color::all();
        return response()->json($colors);
    }
    /**
     * @OA\Post(
     *     path="/api/colors",
     *     summary="Create a new color",
     *     tags={"Colors"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Blue")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Color created",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Blue")
     *         )
     *     )
     * )
     */



    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:colors,name',
        ]);

        $color = Color::create([
            'name' => $request->name,
        ]);

        return response()->json($color, 201);
    }
   /**
     * @OA\Get(
     *     path="/api/colors/{id}",
     *     summary="Get a color by ID",
     *     tags={"Colors"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Color found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Red")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Color not found"
     *     )
     * )
     */
    public function show($id)
    {
        $color = Color::findOrFail($id);
        return response()->json($color);
    }
 /**
     * @OA\Put(
     *     path="/api/colors/{id}",
     *     summary="Update an existing color",
     *     tags={"Colors"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Green")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Color updated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Green")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Color not found"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $color = Color::findOrFail($id);
        $color->update([
            'name' => $request->name,
        ]);

        return response()->json($color);
    }
    /**
     * @OA\Delete(
     *     path="/api/colors/{id}",
     *     summary="Delete a color",
     *     tags={"Colors"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Color deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Color not found"
     *     )
     * )
     */

    public function destroy($id)
    {
        $color = Color::findOrFail($id);
        $color->delete();

        return response()->json(null, 204);
    }
}
