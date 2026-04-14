<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdSubmission;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminAdController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/ads",
     *     tags={"Admin – Ads"},
     *     summary="List all ads",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", example=15)),
     *     @OA\Parameter(name="search",   in="query", @OA\Schema(type="string"), description="Search by title or title_ar"),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of ads",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id",                   type="integer", example=1),
     *                         @OA\Property(property="title",                type="string",  example="Ramadan Special Offer"),
     *                         @OA\Property(property="title_ar",             type="string",  example="عرض رمضان الخاص",        nullable=true),
     *                         @OA\Property(property="description",          type="string",  example="Get 30% off",             nullable=true),
     *                         @OA\Property(property="description_ar",       type="string",  example="احصل على خصم 30%",        nullable=true),
     *                         @OA\Property(property="type",                 type="string",  enum={"photo","video"}),
     *                         @OA\Property(property="image",                type="string",  nullable=true),
     *                         @OA\Property(property="media_url",            type="string",  nullable=true),
     *                         @OA\Property(property="button_text",          type="string",  example="I am interested",         nullable=true),
     *                         @OA\Property(property="button_ar",            type="string",  example="أنا مهتم",                nullable=true),
     *                         @OA\Property(property="google_sheet_id",      type="string",  nullable=true),
     *                         @OA\Property(property="is_active",            type="boolean", example=true),
     *                         @OA\Property(property="ad_submissions_count", type="integer", example=42)
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $search  = $request->input('search');

        $query = Banner::where('has_form', true)->orderBy('order');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title',    'like', "%{$search}%")
                  ->orWhere('title_ar', 'like', "%{$search}%");
            });
        }

        $ads = $query->withCount('adSubmissions')->paginate($perPage);

        $ads->getCollection()->transform(fn ($b) => $this->formatAd($b));

        return response()->json(['success' => true, 'data' => $ads]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/ads",
     *     tags={"Admin – Ads"},
     *     summary="Create a new ad",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title"},
     *             @OA\Property(property="title",           type="string",  example="Ramadan Special Offer"),
     *             @OA\Property(property="title_ar",        type="string",  example="عرض رمضان الخاص"),
     *             @OA\Property(property="description",     type="string",  example="Get 30% off during Ramadan"),
     *             @OA\Property(property="description_ar",  type="string",  example="احصل على خصم 30% خلال رمضان"),
     *             @OA\Property(property="type",            type="string",  enum={"photo","video"}, example="video"),
     *             @OA\Property(property="image",           type="string",  example="https://cdn.dabapp.com/ads/ramadan-thumb.jpg"),
     *             @OA\Property(property="media_url",       type="string",  example="https://cdn.dabapp.com/ads/ramadan.mp4"),
     *             @OA\Property(property="button_text",     type="string",  example="I am interested"),
     *             @OA\Property(property="button_ar",       type="string",  example="أنا مهتم"),
     *             @OA\Property(property="google_sheet_id", type="string",  example="15ln515Ecn1lw1ZdHlqe3BdDuXzlxskZLmfdpa7wjXNU"),
     *             @OA\Property(property="order",           type="integer", example=1),
     *             @OA\Property(property="is_active",       type="boolean", example=true),
     *             @OA\Property(property="start_date",      type="string",  format="date", example="2026-04-14"),
     *             @OA\Property(property="end_date",        type="string",  format="date", example="2026-12-31")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Ad created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'           => 'required|string|max:255',
            'title_ar'        => 'nullable|string|max:255',
            'description'     => 'nullable|string',
            'description_ar'  => 'nullable|string',
            'type'            => 'nullable|in:photo,video',
            'image'           => 'nullable|string',
            'media_url'       => 'nullable|string',
            'button_text'     => 'nullable|string|max:100',
            'button_ar'       => 'nullable|string|max:100',
            'google_sheet_id' => 'nullable|string|max:255',
            'order'           => 'nullable|integer|min:0',
            'is_active'       => 'nullable|boolean',
            'start_date'      => 'nullable|date',
            'end_date'        => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $ad = Banner::create([
            'title'           => $request->title,
            'title_ar'        => $request->title_ar,
            'description'     => $request->description,
            'description_ar'  => $request->description_ar,
            'type'            => $request->type ?? 'photo',
            'image'           => $request->image,
            'media_url'       => $request->media_url,
            'button_text'     => $request->button_text,
            'button_ar'       => $request->button_ar,
            'has_form'        => true,
            'google_sheet_id' => $request->google_sheet_id,
            'link'            => $request->link,
            'order'           => $request->order ?? 0,
            'is_active'       => $request->is_active ?? true,
            'start_date'      => $request->start_date,
            'end_date'        => $request->end_date,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ad created successfully',
            'data'    => $this->formatAd($ad),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/ads/{id}",
     *     tags={"Admin – Ads"},
     *     summary="Get a single ad",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), example=1),
     *     @OA\Response(
     *         response=200,
     *         description="Ad details with submission count",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",                   type="integer", example=1),
     *                 @OA\Property(property="title",                type="string",  example="Ramadan Special Offer"),
     *                 @OA\Property(property="title_ar",             type="string",  example="عرض رمضان الخاص",   nullable=true),
     *                 @OA\Property(property="description",          type="string",  nullable=true),
     *                 @OA\Property(property="description_ar",       type="string",  nullable=true),
     *                 @OA\Property(property="button_text",          type="string",  nullable=true),
     *                 @OA\Property(property="button_ar",            type="string",  nullable=true),
     *                 @OA\Property(property="ad_submissions_count", type="integer", example=42)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $ad = Banner::where('has_form', true)->withCount('adSubmissions')->find($id);

        if (!$ad) {
            return response()->json(['success' => false, 'message' => 'Ad not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->formatAd($ad)]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/ads/{id}",
     *     tags={"Admin – Ads"},
     *     summary="Update an ad",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), example=1),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="title",           type="string"),
     *             @OA\Property(property="title_ar",        type="string",  example="عرض رمضان الخاص"),
     *             @OA\Property(property="description",     type="string"),
     *             @OA\Property(property="description_ar",  type="string",  example="احصل على خصم 30% خلال رمضان"),
     *             @OA\Property(property="type",            type="string",  enum={"photo","video"}),
     *             @OA\Property(property="button_text",     type="string"),
     *             @OA\Property(property="button_ar",       type="string",  example="أنا مهتم"),
     *             @OA\Property(property="google_sheet_id", type="string"),
     *             @OA\Property(property="is_active",       type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Ad updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $ad = Banner::where('has_form', true)->find($id);

        if (!$ad) {
            return response()->json(['success' => false, 'message' => 'Ad not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title'           => 'sometimes|required|string|max:255',
            'title_ar'        => 'nullable|string|max:255',
            'description'     => 'nullable|string',
            'description_ar'  => 'nullable|string',
            'type'            => 'nullable|in:photo,video',
            'image'           => 'nullable|string',
            'media_url'       => 'nullable|string',
            'button_text'     => 'nullable|string|max:100',
            'button_ar'       => 'nullable|string|max:100',
            'google_sheet_id' => 'nullable|string|max:255',
            'order'           => 'nullable|integer|min:0',
            'is_active'       => 'nullable|boolean',
            'start_date'      => 'nullable|date',
            'end_date'        => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $ad->update($request->only([
            'title', 'title_ar',
            'description', 'description_ar',
            'type', 'image', 'media_url',
            'button_text', 'button_ar',
            'google_sheet_id', 'link',
            'order', 'is_active', 'start_date', 'end_date',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Ad updated successfully',
            'data'    => $this->formatAd($ad),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/ads/{id}",
     *     tags={"Admin – Ads"},
     *     summary="Delete an ad",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), example=1),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $ad = Banner::where('has_form', true)->find($id);

        if (!$ad) {
            return response()->json(['success' => false, 'message' => 'Ad not found'], 404);
        }

        $ad->delete();

        return response()->json(['success' => true, 'message' => 'Ad deleted successfully']);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/ads/{id}/toggle",
     *     tags={"Admin – Ads"},
     *     summary="Toggle ad active/inactive",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), example=1),
     *     @OA\Response(
     *         response=200,
     *         description="Status toggled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success",   type="boolean", example=true),
     *             @OA\Property(property="is_active", type="boolean", example=false)
     *         )
     *     )
     * )
     */
    public function toggle($id)
    {
        $ad = Banner::where('has_form', true)->find($id);

        if (!$ad) {
            return response()->json(['success' => false, 'message' => 'Ad not found'], 404);
        }

        $ad->is_active = !$ad->is_active;
        $ad->save();

        return response()->json([
            'success'   => true,
            'message'   => 'Status updated',
            'is_active' => $ad->is_active,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/ads/{id}/submissions",
     *     tags={"Admin – Ads"},
     *     summary="Get all lead submissions for an ad",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id",       in="path",  required=true, @OA\Schema(type="integer"), example=1),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", example=25)),
     *     @OA\Parameter(name="search",   in="query", @OA\Schema(type="string"), description="Search by first_name, last_name or phone"),
     *     @OA\Response(
     *         response=200,
     *         description="List of submissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="ad", type="object",
     *                 @OA\Property(property="id",    type="integer", example=1),
     *                 @OA\Property(property="title", type="string",  example="Ramadan Special Offer")
     *             ),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id",              type="integer", example=1),
     *                         @OA\Property(property="user_id",         type="integer", example=5,     nullable=true),
     *                         @OA\Property(property="user_name",       type="string",  example="Ali", nullable=true),
     *                         @OA\Property(property="first_name",      type="string",  example="Ahmed"),
     *                         @OA\Property(property="last_name",       type="string",  example="Benali"),
     *                         @OA\Property(property="phone",           type="string",  example="0551234567"),
     *                         @OA\Property(property="city_id",         type="integer", example=1),
     *                         @OA\Property(property="city",            type="string",  example="Dubai"),
     *                         @OA\Property(property="synced_to_sheet", type="boolean", example=true),
     *                         @OA\Property(property="submitted_at",    type="string",  example="2026-04-14 10:30:00")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Ad not found")
     * )
     */
    public function submissions(Request $request, $id)
    {
        $ad = Banner::where('has_form', true)->find($id);

        if (!$ad) {
            return response()->json(['success' => false, 'message' => 'Ad not found'], 404);
        }

        $perPage = $request->input('per_page', 25);
        $search  = $request->input('search');

        $query = AdSubmission::where('banner_id', $id)->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name',  'like', "%{$search}%")
                  ->orWhere('phone',      'like', "%{$search}%");
            });
        }

        $submissions = $query->with(['city', 'user'])->paginate($perPage);

        $submissions->getCollection()->transform(fn ($s) => [
            'id'              => $s->id,
            'user_id'         => $s->user_id,
            'user_name'       => $s->user?->name,
            'first_name'      => $s->first_name,
            'last_name'       => $s->last_name,
            'phone'           => $s->phone,
            'email'           => $s->email,
            'city_id'         => $s->city_id,
            'city'            => $s->city?->name,
            'synced_to_sheet' => $s->synced_to_sheet,
            'submitted_at'    => $s->created_at->format('Y-m-d H:i:s'),
        ]);

        return response()->json([
            'success' => true,
            'ad'      => ['id' => $ad->id, 'title' => $ad->title],
            'data'    => $submissions,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    private function formatAd(Banner $b): array
    {
        return [
            'id'                   => $b->id,
            'title'                => $b->title,
            'title_ar'             => $b->title_ar,
            'description'          => $b->description,
            'description_ar'       => $b->description_ar,
            'type'                 => $b->type ?? 'photo',
            'image'                => $b->image,
            'media_url'            => $b->media_url,
            'button_text'          => $b->button_text,
            'button_ar'            => $b->button_ar,
            'google_sheet_id'      => $b->google_sheet_id,
            'order'                => $b->order,
            'is_active'            => (bool) $b->is_active,
            'start_date'           => $b->start_date ? (string) $b->start_date : null,
            'end_date'             => $b->end_date   ? (string) $b->end_date   : null,
            'ad_submissions_count' => $b->ad_submissions_count ?? null,
            'created_at'           => $b->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
