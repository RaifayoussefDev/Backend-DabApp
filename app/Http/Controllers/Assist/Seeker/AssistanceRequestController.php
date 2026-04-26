<?php

namespace App\Http\Controllers\Assist\Seeker;

use App\Http\Controllers\Assist\AssistBaseController;
use App\Http\Requests\Assist\CreateAssistanceRequestRequest;
use App\Http\Requests\Assist\RateHelperRequest;
use App\Models\Assist\AssistanceRequest;
use App\Models\Assist\Rating;
use App\Models\MyGarage;
use App\Services\Assist\HelperMatchingService;
use App\Services\Assist\AssistNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Assist - Seeker",
 *     description="Endpoints for riders seeking roadside assistance"
 * )
 */
class AssistanceRequestController extends AssistBaseController
{
    public function __construct(
        private readonly HelperMatchingService   $matchingService,
        private readonly AssistNotificationService $notificationService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/assist/seeker/requests",
     *     summary="List the authenticated seeker's assistance requests",
     *     description="Returns a paginated history of all assistance requests made by the seeker. Supports filtering by status.",
     *     tags={"Assist - Seeker"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status", in="query", required=false,
     *         description="Filter by request status",
     *         @OA\Schema(type="string", enum={"pending","accepted","en_route","arrived","completed","cancelled"}, example="completed")
     *     ),
     *     @OA\Parameter(name="page", in="query", required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of seeker requests",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page",     type="integer", example=15),
     *                 @OA\Property(property="total",        type="integer", example=8),
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",             type="integer", example=12),
     *                         @OA\Property(property="status",         type="string",  example="completed"),
     *                         @OA\Property(property="status_label",   type="object",
     *                             @OA\Property(property="en", type="string", example="Completed"),
     *                             @OA\Property(property="ar", type="string", example="مكتمل")
     *                         ),
     *                         @OA\Property(property="location_label", type="string",  example="King Fahd Road, Riyadh – near Exit 7"),
     *                         @OA\Property(property="description",    type="string",  nullable=true, example="My rear tire is flat."),
     *                         @OA\Property(property="created_at",     type="string",  format="date-time"),
     *                         @OA\Property(property="completed_at",   type="string",  format="date-time", nullable=true),
     *                         @OA\Property(property="cancelled_at",   type="string",  format="date-time", nullable=true),
     *                         @OA\Property(property="expertise_types", type="array",
     *                             @OA\Items(type="object",
     *                                 @OA\Property(property="id",   type="integer", example=1),
     *                                 @OA\Property(property="name", type="string",  example="tire_repair"),
     *                                 @OA\Property(property="icon", type="string",  example="tire_repair")
     *                             )
     *                         ),
     *                         @OA\Property(property="helper", type="object", nullable=true,
     *                             @OA\Property(property="id",         type="integer", example=2),
     *                             @OA\Property(property="first_name", type="string",  example="Ahmed"),
     *                             @OA\Property(property="last_name",  type="string",  example="Al-Rashid")
     *                         ),
     *                         @OA\Property(property="rating", type="object", nullable=true,
     *                             @OA\Property(property="stars",   type="integer", example=5),
     *                             @OA\Property(property="comment", type="string",  example="Super fast!")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = AssistanceRequest::with([
            'expertiseTypes:id,name,icon',
            'helper:id,first_name,last_name',
            'rating:id,request_id,stars,comment',
        ])
            ->where('seeker_id', Auth::id())
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->success($query->paginate(15));
    }

    /**
     * @OA\Get(
     *     path="/api/assist/seeker/garage",
     *     summary="Get the authenticated seeker's motorcycle garage",
     *     description="Returns all motorcycles registered in the user's garage. Use the garage `id` as `motorcycle_id` when creating an assistance request.",
     *     tags={"Assist - Seeker"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of motorcycles",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id",         type="integer", example=15),
     *                     @OA\Property(property="title",      type="string",  nullable=true, example="My daily ride"),
     *                     @OA\Property(property="picture",    type="string",  nullable=true, example=null),
     *                     @OA\Property(property="is_default", type="boolean", example=true),
     *                     @OA\Property(property="brand",      type="string",  example="BMW"),
     *                     @OA\Property(property="model",      type="string",  example="F 900 R"),
     *                     @OA\Property(property="year",       type="integer", example=2022)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function garage(): JsonResponse
    {
        $motorcycles = MyGarage::where('user_id', Auth::id())
            ->with(['brand', 'model', 'year'])
            ->get()
            ->map(fn($m) => [
                'id'         => $m->id,
                'title'      => $m->title,
                'picture'    => $m->picture,
                'is_default' => $m->is_default,
                'brand'      => $m->brand?->name,
                'model'      => $m->model?->name ?? '#' . $m->model_id,
                'year'       => $m->year?->year ?? $m->year_id,
            ]);

        return $this->success($motorcycles);
    }

    /**
     * @OA\Post(
     *     path="/api/assist/seeker/request",
     *     summary="Create a new assistance request",
     *     description="Creates a pending request and notifies nearby available helpers who match the required expertise. Photos must be uploaded first via the upload API, then pass the returned URLs here.",
     *     tags={"Assist - Seeker"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"expertise_type_ids","latitude","longitude","location_label"},
     *             example={
     *                 "expertise_type_ids": {1, 3},
     *                 "latitude": 24.7140,
     *                 "longitude": 46.6750,
     *                 "location_label": "King Fahd Road, Riyadh – near Exit 7",
     *                 "description": "My rear tire is completely flat. Stuck on the side of the road.",
     *                 "motorcycle_id": 15,
     *                 "photo_urls": {
     *                     "https://cdn.example.com/uploads/photo1.jpg",
     *                     "https://cdn.example.com/uploads/photo2.jpg"
     *                 }
     *             },
     *             @OA\Property(property="expertise_type_ids", type="array",
     *                 description="One or more expertise type IDs (from GET /api/assist/expertise-types)",
     *                 @OA\Items(type="integer"),
     *                 example={1, 3}
     *             ),
     *             @OA\Property(property="latitude",       type="number", format="float", example=24.7140,
     *                 description="Rider's current latitude (-90 to 90)"),
     *             @OA\Property(property="longitude",      type="number", format="float", example=46.6750,
     *                 description="Rider's current longitude (-180 to 180)"),
     *             @OA\Property(property="location_label", type="string", example="King Fahd Road, Riyadh – near Exit 7",
     *                 description="Human-readable location label"),
     *             @OA\Property(property="description",    type="string", nullable=true,
     *                 example="My rear tire is completely flat. Stuck on the side of the road."),
     *             @OA\Property(property="motorcycle_id",  type="integer", nullable=true, example=15,
     *                 description="Optional: garage motorcycle ID from GET /api/assist/seeker/garage"),
     *             @OA\Property(property="photo_urls", type="array", nullable=true,
     *                 description="Optional: up to 5 photo URLs returned by the upload API",
     *                 @OA\Items(type="string", format="url"),
     *                 example={"https://cdn.example.com/uploads/photo1.jpg","https://cdn.example.com/uploads/photo2.jpg"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Request created and helpers notified",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Assistance request created. Looking for helpers."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="request", type="object",
     *                     @OA\Property(property="id",             type="integer", example=12),
     *                     @OA\Property(property="status",         type="string",  example="pending"),
     *                     @OA\Property(property="location_label", type="string",  example="King Fahd Road, Riyadh – near Exit 7"),
     *                     @OA\Property(property="latitude",       type="number",  format="float", example=24.714),
     *                     @OA\Property(property="longitude",      type="number",  format="float", example=46.675),
     *                     @OA\Property(property="description",    type="string",  example="My rear tire is completely flat."),
     *                     @OA\Property(property="created_at",     type="string",  format="date-time"),
     *                     @OA\Property(property="expertise_types", type="array",
     *                         @OA\Items(type="object",
     *                             @OA\Property(property="id",   type="integer", example=1),
     *                             @OA\Property(property="name", type="string",  example="tire_repair"),
     *                             @OA\Property(property="icon", type="string",  example="tire_repair")
     *                         )
     *                     ),
     *                     @OA\Property(property="photos", type="array",
     *                         @OA\Items(type="object",
     *                             @OA\Property(property="id",   type="integer", example=1),
     *                             @OA\Property(property="path", type="string",  example="https://cdn.example.com/uploads/photo1.jpg")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="helpers_notified", type="integer", example=3)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=409, description="Already has an active request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string",
     *                 example="You already have an active assistance request (status: pending). Please wait for it to be completed or cancel it before creating a new one.")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string",  example="Validation failed."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="expertise_type_ids", type="array",
     *                     @OA\Items(type="string", example="The expertise type ids field is required.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function store(CreateAssistanceRequestRequest $request): JsonResponse
    {
        $user = Auth::user();

        $active = AssistanceRequest::where('seeker_id', $user->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->first();

        if ($active) {
            return $this->error(
                'You already have an active assistance request (status: ' . $active->status . '). ' .
                'Please wait for it to be completed or cancel it before creating a new one.',
                409
            );
        }

        DB::beginTransaction();
        try {
            $assistRequest = AssistanceRequest::create([
                'seeker_id'      => $user->id,
                'latitude'       => $request->latitude,
                'longitude'      => $request->longitude,
                'location_label' => $request->location_label,
                'description'    => $request->description,
                'motorcycle_id'  => $request->motorcycle_id,
                'status'         => 'pending',
            ]);

            $assistRequest->expertiseTypes()->sync($request->expertise_type_ids);

            // Handle photo URLs (uploaded separately via upload API)
            if ($request->filled('photo_urls')) {
                foreach ($request->photo_urls as $url) {
                    $assistRequest->photos()->create(['path' => $url]);
                }
            }

            $helpers = $this->matchingService->findNearby($assistRequest);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to create assistance request.', 500);
        }

        $assistRequest->load(['expertiseTypes', 'photos', 'motorcycle.brand', 'motorcycle.model', 'motorcycle.year']);

        if ($m = $assistRequest->motorcycle) {
            $assistRequest->setRelation('motorcycle', [
                'id'    => $m->id,
                'brand' => $m->brand?->name,
                'model' => $m->model?->name ?? '#' . $m->model_id,
                'year'  => $m->year?->year ?? $m->year_id,
            ]);
        }

        return $this->success([
            'request'          => $assistRequest,
            'helpers_notified' => $helpers->count(),
        ], 'Assistance request created. Looking for helpers.', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/assist/seeker/request/{id}",
     *     summary="Get assistance request details with seeker's garage",
     *     description="Returns the full request details alongside the seeker's motorcycle garage, so the client can display or link a motorcycle to the request.",
     *     tags={"Assist - Seeker"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="Assistance request ID",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Request details and garage",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="request", type="object",
     *                     @OA\Property(property="id",             type="integer", example=12),
     *                     @OA\Property(property="status",         type="string",  example="pending"),
     *                     @OA\Property(property="description",    type="string",  example="My rear tire is completely flat."),
     *                     @OA\Property(property="location_label", type="string",  example="King Fahd Road, Riyadh – near Exit 7"),
     *                     @OA\Property(property="latitude",       type="number",  format="float", example=24.714),
     *                     @OA\Property(property="longitude",      type="number",  format="float", example=46.675),
     *                     @OA\Property(property="accepted_at",    type="string",  format="date-time", nullable=true),
     *                     @OA\Property(property="arrived_at",     type="string",  format="date-time", nullable=true),
     *                     @OA\Property(property="completed_at",   type="string",  format="date-time", nullable=true),
     *                     @OA\Property(property="cancelled_at",   type="string",  format="date-time", nullable=true),
     *                     @OA\Property(property="expertise_types", type="array",
     *                         @OA\Items(type="object",
     *                             @OA\Property(property="id",   type="integer", example=1),
     *                             @OA\Property(property="name", type="string",  example="tire_repair"),
     *                             @OA\Property(property="icon", type="string",  example="tire_repair")
     *                         )
     *                     ),
     *                     @OA\Property(property="photos", type="array",
     *                         @OA\Items(type="object",
     *                             @OA\Property(property="id",   type="integer", example=1),
     *                             @OA\Property(property="path", type="string",  example="https://cdn.example.com/uploads/photo1.jpg")
     *                         )
     *                     ),
     *                     @OA\Property(property="helper", type="object", nullable=true,
     *                         @OA\Property(property="id",              type="integer", example=2),
     *                         @OA\Property(property="first_name",      type="string",  example="Ahmed"),
     *                         @OA\Property(property="last_name",       type="string",  example="Al-Rashid"),
     *                         @OA\Property(property="phone",           type="string",  example="+966501234567"),
     *                         @OA\Property(property="profile_picture", type="string",  nullable=true, example=null)
     *                     ),
     *                     @OA\Property(property="rating", type="object", nullable=true,
     *                         @OA\Property(property="stars",   type="integer", example=5),
     *                         @OA\Property(property="comment", type="string",  example="Super fast and professional!")
     *                     )
     *                 ),
     *                 @OA\Property(property="garage", type="array",
     *                     description="Seeker's registered motorcycles",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",         type="integer", example=15),
     *                         @OA\Property(property="title",      type="string",  nullable=true, example="My daily ride"),
     *                         @OA\Property(property="picture",    type="string",  nullable=true, example=null),
     *                         @OA\Property(property="is_default", type="boolean", example=true),
     *                         @OA\Property(property="brand",      type="string",  example="BMW"),
     *                         @OA\Property(property="model",      type="string",  example="F 900 R"),
     *                         @OA\Property(property="year",       type="integer", example=2022)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="This request does not belong to you"),
     *     @OA\Response(response=404, description="Request not found")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $request = AssistanceRequest::with([
            'expertiseTypes', 'photos', 'motorcycle.brand', 'motorcycle.model', 'motorcycle.year',
            'helper:id,first_name,last_name,phone,profile_picture',
            'rating',
        ])->find($id);

        if (!$request) {
            return $this->error('Assistance request not found.', 404);
        }

        if ($request->seeker_id !== Auth::id()) {
            return $this->error('Forbidden.', 403);
        }

        $garage = MyGarage::where('user_id', Auth::id())
            ->with(['brand', 'model', 'year'])
            ->get()
            ->map(fn($m) => [
                'id'         => $m->id,
                'title'      => $m->title,
                'picture'    => $m->picture,
                'is_default' => $m->is_default,
                'brand'      => $m->brand?->name,
                'model'      => $m->model?->name ?? '#' . $m->model_id,
                'year'       => $m->year?->year ?? $m->year_id,
            ]);

        if ($m = $request->motorcycle) {
            $request->setRelation('motorcycle', [
                'id'    => $m->id,
                'brand' => $m->brand?->name,
                'model' => $m->model?->name ?? '#' . $m->model_id,
                'year'  => $m->year?->year ?? $m->year_id,
            ]);
        }

        return $this->success([
            'request' => $request,
            'garage'  => $garage,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/assist/seeker/request/{id}",
     *     summary="Cancel an assistance request",
     *     description="Only pending or accepted requests can be cancelled.",
     *     tags={"Assist - Seeker"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="Assistance request ID",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             example={"cancel_reason": "I found help from a passing driver"},
     *             @OA\Property(property="cancel_reason", type="string", nullable=true,
     *                 example="I found help from a passing driver")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Request cancelled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Request cancelled.")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="This request does not belong to you"),
     *     @OA\Response(response=404, description="Request not found"),
     *     @OA\Response(response=422, description="Cannot cancel a completed or already cancelled request")
     * )
     */
    public function cancel(string $id): JsonResponse
    {
        $assistRequest = AssistanceRequest::find($id);

        if (!$assistRequest) {
            return $this->error('Assistance request not found.', 404);
        }

        if ($assistRequest->seeker_id !== Auth::id()) {
            return $this->error('Forbidden.', 403);
        }

        if (in_array($assistRequest->status, ['completed', 'cancelled'])) {
            return $this->error('Cannot cancel a request that is already ' . $assistRequest->status . '.', 422);
        }

        $assistRequest->update([
            'status'        => 'cancelled',
            'cancelled_at'  => now(),
            'cancel_reason' => request('cancel_reason'),
        ]);

        // Notify helper if one was assigned
        if ($assistRequest->helper_id) {
            $this->notificationService->notify(
                $assistRequest->helper,
                'cancelled',
                $assistRequest
            );
        }

        return $this->success([], 'Request cancelled.');
    }

    /**
     * @OA\Post(
     *     path="/api/assist/seeker/request/{id}/rate",
     *     summary="Rate the helper after a completed mission",
     *     description="Can only be submitted once, and only when the request status is `completed`.",
     *     tags={"Assist - Seeker"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="Assistance request ID",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"stars"},
     *             example={"stars": 5, "comment": "Super fast and professional, saved my day!"},
     *             @OA\Property(property="stars",   type="integer", minimum=1, maximum=5, example=5,
     *                 description="Rating from 1 (poor) to 5 (excellent)"),
     *             @OA\Property(property="comment", type="string", nullable=true,
     *                 example="Super fast and professional, saved my day!")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Rating submitted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Rating submitted successfully."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",         type="integer", example=1),
     *                 @OA\Property(property="request_id", type="integer", example=12),
     *                 @OA\Property(property="rater_id",   type="integer", example=65),
     *                 @OA\Property(property="rated_id",   type="integer", example=2),
     *                 @OA\Property(property="stars",      type="integer", example=5),
     *                 @OA\Property(property="comment",    type="string",  example="Super fast and professional!")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="This request does not belong to you"),
     *     @OA\Response(response=404, description="Request not found"),
     *     @OA\Response(response=422, description="Request not completed yet, or already rated")
     * )
     */
    public function rate(RateHelperRequest $request, string $id): JsonResponse
    {
        $assistRequest = AssistanceRequest::find($id);

        if (!$assistRequest) {
            return $this->error('Assistance request not found.', 404);
        }

        if ($assistRequest->seeker_id !== Auth::id()) {
            return $this->error('Forbidden.', 403);
        }

        if ($assistRequest->status !== 'completed') {
            return $this->error('You can only rate a completed request.', 422);
        }

        if ($assistRequest->rating()->exists()) {
            return $this->error('This request has already been rated.', 422);
        }

        $rating = Rating::create([
            'request_id' => $assistRequest->id,
            'rater_id'   => Auth::id(),
            'rated_id'   => $assistRequest->helper_id,
            'stars'      => $request->stars,
            'comment'    => $request->comment,
        ]);

        // Recalculate helper average rating
        $helperProfile = $assistRequest->helper?->helperProfile;
        if ($helperProfile) {
            $avg = Rating::where('rated_id', $assistRequest->helper_id)->avg('stars');
            $helperProfile->update(['rating' => round($avg, 2)]);
        }

        // Notify helper
        $this->notificationService->notify($assistRequest->helper, 'rated', $assistRequest);

        return $this->success($rating, 'Rating submitted successfully.', 201);
    }
}
