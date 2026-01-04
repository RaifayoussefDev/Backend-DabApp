<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Admin Report Types",
 *     description="API Endpoints for Managing Report Types"
 * )
 */
class ReportTypeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/report-types",
     *     summary="List all report types",
     *     tags={"Admin Report Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of report types",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="code", type="string"),
     *                     @OA\Property(property="name_en", type="string"),
     *                     @OA\Property(property="name_ar", type="string"),
     *                     @OA\Property(property="is_active", type="boolean")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        return response()->json(ReportType::all());
    }

    /**
     * @OA\Post(
     *     path="/api/admin/report-types",
     *     summary="Create a new report type",
     *     tags={"Admin Report Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code", "name_en", "name_ar"},
     *             @OA\Property(property="code", type="string", description="Unique code (e.g. guide, listing)", example="story"),
     *             @OA\Property(property="name_en", type="string", example="Story"),
     *             @OA\Property(property="name_ar", type="string", example="قصة"),
     *             @OA\Property(property="is_active", type="boolean", default=true, example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="name_en", type="string"),
     *             @OA\Property(property="name_ar", type="string"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50|unique:report_types,code',
            'name_en' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $type = ReportType::create($request->all());

        return response()->json($type, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/report-types/{id}",
     *     summary="Get specific report type",
     *     tags={"Admin Report Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Report Type details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="name_en", type="string"),
     *             @OA\Property(property="name_ar", type="string"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        return response()->json(ReportType::findOrFail($id));
    }

    /**
     * @OA\Put(
     *     path="/api/admin/report-types/{id}",
     *     summary="Update a report type",
     *     tags={"Admin Report Types"},
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
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="name_en", type="string"),
     *             @OA\Property(property="name_ar", type="string"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="name_en", type="string"),
     *             @OA\Property(property="name_ar", type="string"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $type = ReportType::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'code' => 'string|max:50|unique:report_types,code,' . $id,
            'name_en' => 'string|max:255',
            'name_ar' => 'string|max:255',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $type->update($request->all());

        return response()->json($type);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/report-types/{id}",
     *     summary="Delete a report type",
     *     tags={"Admin Report Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deleted successfully"
     *     )
     * )
     */
    public function destroy($id)
    {
        $type = ReportType::findOrFail($id);
        $type->delete();

        return response()->json(['message' => 'Report type deleted successfully']);
    }
}
