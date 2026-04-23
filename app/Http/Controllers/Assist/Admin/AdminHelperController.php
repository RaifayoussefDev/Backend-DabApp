<?php

namespace App\Http\Controllers\Assist\Admin;

use App\Http\Controllers\Assist\AssistBaseController;
use App\Models\Assist\AssistanceRequest;
use App\Models\Assist\HelperProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Assist - Admin Helpers",
 *     description="Admin management of helper profiles"
 * )
 */
class AdminHelperController extends AssistBaseController
{
    /**
     * @OA\Get(
     *     path="/api/assist/admin/helpers",
     *     summary="List all helper profiles with optional filters",
     *     tags={"Assist - Admin Helpers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status", in="query", required=false,
     *         description="Filter by approval status",
     *         @OA\Schema(type="string", enum={"pending","accepted","rejected"}, example="pending")
     *     ),
     *     @OA\Parameter(name="is_available", in="query", required=false,
     *         description="Filter by availability",
     *         @OA\Schema(type="boolean", example=false)
     *     ),
     *     @OA\Parameter(name="level", in="query", required=false,
     *         description="Filter by helper level",
     *         @OA\Schema(type="string", enum={"standard","elite","vanguard"}, example="vanguard")
     *     ),
     *     @OA\Parameter(name="expertise", in="query", required=false,
     *         description="Filter by expertise type name (e.g. tire_repair)",
     *         @OA\Schema(type="string", example="tire_repair")
     *     ),
     *     @OA\Parameter(name="page", in="query", required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of helpers",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=20),
     *                 @OA\Property(property="total", type="integer", example=45),
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(ref="#/components/schemas/HelperProfile")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = HelperProfile::with(['user:id,first_name,last_name,email,phone', 'expertiseTypes']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('is_available')) {
            $query->where('is_available', filter_var($request->is_available, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        if ($request->filled('expertise')) {
            $query->whereHas('expertiseTypes', function ($q) use ($request) {
                $q->where('name', $request->expertise);
            });
        }

        $helpers = $query->orderByDesc('rating')->paginate(20);

        return $this->success($helpers);
    }

    /**
     * @OA\Get(
     *     path="/api/assist/admin/helpers/{id}",
     *     summary="Get a single helper profile detail",
     *     tags={"Assist - Admin Helpers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="HelperProfile ID",
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Response(response=200, description="Helper detail",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/HelperProfile")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $helper = HelperProfile::with([
            'user:id,first_name,last_name,email,phone,profile_picture',
            'expertiseTypes',
        ])->find($id);

        if (!$helper) {
            return $this->error('Helper profile not found.', 404);
        }

        return $this->success($helper);
    }

    /**
     * @OA\Patch(
     *     path="/api/assist/admin/helpers/{id}/status",
     *     summary="Update helper approval status (pending / accepted / rejected)",
     *     tags={"Assist - Admin Helpers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="HelperProfile ID",
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string",
     *                 enum={"pending","accepted","rejected"}, example="accepted")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Status updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Helper accepted successfully."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="status", type="string", example="accepted")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,accepted,rejected',
        ]);

        $helper = HelperProfile::find($id);

        if (!$helper) {
            return $this->error('Helper profile not found.', 404);
        }

        $helper->update(['status' => $request->status]);

        $messages = [
            'pending'  => 'Helper status set to pending.',
            'accepted' => 'Helper accepted successfully.',
            'rejected' => 'Helper rejected successfully.',
        ];

        return $this->success(['status' => $helper->status], $messages[$helper->status]);
    }

    /**
     * @OA\Get(
     *     path="/api/assist/admin/helpers/{id}/requests",
     *     summary="List all assistance requests handled by a specific helper",
     *     tags={"Assist - Admin Helpers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="HelperProfile ID",
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Parameter(name="status", in="query", required=false,
     *         description="Filter by request status",
     *         @OA\Schema(type="string", enum={"accepted","en_route","arrived","completed","cancelled"}, example="completed")
     *     ),
     *     @OA\Parameter(name="page", in="query", required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of missions for this helper",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page",     type="integer", example=20),
     *                 @OA\Property(property="total",        type="integer", example=47),
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",             type="integer", example=12),
     *                         @OA\Property(property="status",         type="string",  example="completed"),
     *                         @OA\Property(property="status_label",   type="object",
     *                             @OA\Property(property="en", type="string", example="Completed"),
     *                             @OA\Property(property="ar", type="string", example="مكتمل")
     *                         ),
     *                         @OA\Property(property="location_label", type="string",  example="King Fahd Road, Riyadh"),
     *                         @OA\Property(property="accepted_at",    type="string",  format="date-time", nullable=true),
     *                         @OA\Property(property="completed_at",   type="string",  format="date-time", nullable=true),
     *                         @OA\Property(property="cancelled_at",   type="string",  format="date-time", nullable=true),
     *                         @OA\Property(property="expertise_types", type="array",
     *                             @OA\Items(type="object",
     *                                 @OA\Property(property="id",   type="integer", example=1),
     *                                 @OA\Property(property="name", type="string",  example="tire_repair")
     *                             )
     *                         ),
     *                         @OA\Property(property="seeker", type="object",
     *                             @OA\Property(property="id",         type="integer", example=65),
     *                             @OA\Property(property="first_name", type="string",  example="Raifa"),
     *                             @OA\Property(property="last_name",  type="string",  example="Youssef")
     *                         ),
     *                         @OA\Property(property="rating", type="object", nullable=true,
     *                             @OA\Property(property="stars",   type="integer", example=5),
     *                             @OA\Property(property="comment", type="string",  example="Excellent service!")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Helper profile not found")
     * )
     */
    public function helperRequests(Request $request, string $id): JsonResponse
    {
        $helper = HelperProfile::find($id);

        if (!$helper) {
            return $this->error('Helper profile not found.', 404);
        }

        $query = AssistanceRequest::with([
            'expertiseTypes:id,name',
            'seeker:id,first_name,last_name',
            'rating:id,request_id,stars,comment',
        ])
            ->where('helper_id', $helper->user_id)
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->success($query->paginate(20));
    }

    /**
     * @OA\Delete(
     *     path="/api/assist/admin/helpers/{id}",
     *     summary="Delete a helper profile",
     *     description="Permanently deletes the helper profile. The user account is not affected.",
     *     tags={"Assist - Admin Helpers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="HelperProfile ID",
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Response(response=200, description="Helper profile deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Helper profile deleted.")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $helper = HelperProfile::find($id);

        if (!$helper) {
            return $this->error('Helper profile not found.', 404);
        }

        $helper->delete();

        return $this->success([], 'Helper profile deleted.');
    }
}
