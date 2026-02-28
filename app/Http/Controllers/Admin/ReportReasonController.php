<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportReason;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Admin Report Reasons",
 *     description="API Endpoints for Managing Report Reasons"
 * )
 */
class ReportReasonController extends Controller
{
    /**
     * @OA\Get(
     *     path="/admin/report-reasons",
     *     summary="List all report reasons",
     *     tags={"Admin Report Reasons"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="type_id",
     *         in="query",
     *         description="Filter by type ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of report reasons",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="report_type_id", type="integer"),
     *                     @OA\Property(
     *                         property="type",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="code", type="string"),
     *                         @OA\Property(property="name_en", type="string"),
     *                         @OA\Property(property="name_ar", type="string")
     *                     ),
     *                     @OA\Property(property="label_en", type="string"),
     *                     @OA\Property(property="label_ar", type="string"),
     *                     @OA\Property(property="is_active", type="boolean")
     *                 )
     *             ),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = ReportReason::query()->with('type');

        if ($request->has('type_id')) {
            $query->where('report_type_id', $request->query('type_id'));
        }

        return response()->json($query->paginate(20));
    }

    /**
     * @OA\Post(
     *     path="/admin/report-reasons",
     *     summary="Create a new report reason",
     *     tags={"Admin Report Reasons"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"report_type_id", "label_en", "label_ar"},
     *             @OA\Property(property="report_type_id", type="integer", description="ID of the report type", example=1),
     *             @OA\Property(property="label_en", type="string", example="Inappropriate content"),
     *             @OA\Property(property="label_ar", type="string", example="محتوى غير لائق"),
     *             @OA\Property(property="is_active", type="boolean", default=true, example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="report_type_id", type="integer"),
     *             @OA\Property(property="label_en", type="string"),
     *             @OA\Property(property="label_ar", type="string"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'report_type_id' => 'required|exists:report_types,id',
            'label_en' => 'required|string|max:255',
            'label_ar' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $reason = ReportReason::create($request->all());

        return response()->json($reason, 201);
    }

    /**
     * @OA\Get(
     *     path="/admin/report-reasons/{id}",
     *     summary="Get specific report reason",
     *     tags={"Admin Report Reasons"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Report Reason details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="report_type_id", type="integer"),
     *             @OA\Property(property="label_en", type="string"),
     *             @OA\Property(property="label_ar", type="string"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        $reason = ReportReason::with('type')->findOrFail($id);
        return response()->json($reason);
    }

    /**
     * @OA\Put(
     *     path="/admin/report-reasons/{id}",
     *     summary="Update a report reason",
     *     tags={"Admin Report Reasons"},
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
     *             @OA\Property(property="report_type_id", type="integer"),
     *             @OA\Property(property="label_en", type="string"),
     *             @OA\Property(property="label_ar", type="string"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="report_type_id", type="integer"),
     *             @OA\Property(property="label_en", type="string"),
     *             @OA\Property(property="label_ar", type="string"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $reason = ReportReason::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'report_type_id' => 'exists:report_types,id',
            'label_en' => 'string|max:255',
            'label_ar' => 'string|max:255',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $reason->update($request->all());

        return response()->json($reason);
    }

    /**
     * @OA\Delete(
     *     path="/admin/report-reasons/{id}",
     *     summary="Delete a report reason",
     *     tags={"Admin Report Reasons"},
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
        $reason = ReportReason::findOrFail($id);
        $reason->delete();

        return response()->json(['message' => 'Report reason deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/admin/report-reasons/types",
     *     summary="Get all report reason types",
     *     tags={"Admin Report Reasons"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of types",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="code", type="string"),
     *                 @OA\Property(property="name_en", type="string"),
     *                 @OA\Property(property="name_ar", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function getTypes()
    {
        $types = \App\Models\ReportType::all();
        return response()->json($types);
    }
}

