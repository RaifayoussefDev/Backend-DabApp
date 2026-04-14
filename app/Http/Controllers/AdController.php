<?php

namespace App\Http\Controllers;

use App\Models\AdSubmission;
use App\Models\Banner;
use App\Services\GoogleSheetsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdController extends Controller
{
    public function __construct(private GoogleSheetsService $sheetsService) {}

    /**
     * @OA\Get(
     *     path="/api/ads",
     *     tags={"Ads"},
     *     summary="Get active ads for mobile app",
     *     description="Returns all active ads with a lead form enabled, within the active date range.",
     *     @OA\Response(
     *         response=200,
     *         description="List of active ads",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id",             type="integer", example=1),
     *                     @OA\Property(property="title",          type="string",  example="Ramadan Special Offer"),
     *                     @OA\Property(property="title_ar",       type="string",  example="عرض رمضان الخاص",          nullable=true),
     *                     @OA\Property(property="description",    type="string",  example="Get 30% off during Ramadan", nullable=true),
     *                     @OA\Property(property="description_ar", type="string",  example="احصل على خصم 30% خلال رمضان", nullable=true),
     *                     @OA\Property(property="type",           type="string",  enum={"photo","video"}, example="video"),
     *                     @OA\Property(property="image",          type="string",  example="https://cdn.dabapp.com/ads/ramadan-thumb.jpg", nullable=true),
     *                     @OA\Property(property="media_url",      type="string",  example="https://cdn.dabapp.com/ads/ramadan.mp4",       nullable=true),
     *                     @OA\Property(property="button_text",    type="string",  example="I am interested",            nullable=true),
     *                     @OA\Property(property="button_ar",      type="string",  example="أنا مهتم",                   nullable=true),
     *                     @OA\Property(property="has_form",       type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $ads = Banner::where('is_active', true)
            ->where('has_form', true)
            ->where(function ($q) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->orderBy('order')
            ->get()
            ->map(fn ($b) => $this->formatAd($b));

        return response()->json(['success' => true, 'data' => $ads]);
    }

    /**
     * @OA\Get(
     *     path="/api/ads/{id}",
     *     tags={"Ads"},
     *     summary="Get a single ad",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), example=1),
     *     @OA\Response(
     *         response=200,
     *         description="Ad details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",             type="integer", example=1),
     *                 @OA\Property(property="title",          type="string",  example="Ramadan Special Offer"),
     *                 @OA\Property(property="title_ar",       type="string",  example="عرض رمضان الخاص",   nullable=true),
     *                 @OA\Property(property="description",    type="string",  nullable=true),
     *                 @OA\Property(property="description_ar", type="string",  nullable=true),
     *                 @OA\Property(property="button_text",    type="string",  nullable=true),
     *                 @OA\Property(property="button_ar",      type="string",  example="أنا مهتم", nullable=true),
     *                 @OA\Property(property="has_form",       type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Ad not found")
     * )
     */
    public function show($id)
    {
        $ad = Banner::where('has_form', true)->find($id);

        if (!$ad) {
            return response()->json(['success' => false, 'message' => 'Ad not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->formatAd($ad)]);
    }

    /**
     * @OA\Post(
     *     path="/api/ads/{id}/submit",
     *     tags={"Ads"},
     *     summary="Submit lead form after viewing an ad",
     *     description="User submits their info after watching the ad. Saved to DB and synced to the linked Google Sheet.",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), example=1),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"first_name","last_name","phone","city_id"},
     *             @OA\Property(property="first_name", type="string",  example="Ahmed"),
     *             @OA\Property(property="last_name",  type="string",  example="Benali"),
     *             @OA\Property(property="phone",      type="string",  example="0551234567"),
     *             @OA\Property(property="email",      type="string",  format="email", example="ahmed@example.com", nullable=true),
     *             @OA\Property(property="city_id",    type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Submission saved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Thank you! Your information has been submitted.")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Ad not found or inactive"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function submit(Request $request, $id)
    {
        $ad = Banner::where('is_active', true)->where('has_form', true)->find($id);

        if (!$ad) {
            return response()->json(['success' => false, 'message' => 'Ad not found or inactive'], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'phone'      => 'required|string|max:20',
            'email'      => 'nullable|email|max:255',
            'city_id'    => 'required|integer|exists:cities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $submission = AdSubmission::create([
            'banner_id'  => $ad->id,
            'user_id'    => auth('api')->id(),
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'phone'      => $request->phone,
            'email'      => $request->email,
            'city_id'    => $request->city_id,
        ]);

        if ($ad->google_sheet_id) {
            $cityName = $submission->city?->name ?? $request->city_id;

            $synced = $this->sheetsService->appendRow(
                $ad->google_sheet_id,
                [
                    $submission->id,
                    $submission->first_name,
                    $submission->last_name,
                    $submission->phone,
                    $submission->email ?? '',
                    $cityName,
                    $submission->created_at->format('Y-m-d H:i:s'),
                ]
            );

            $submission->update(['synced_to_sheet' => $synced]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Thank you! Your information has been submitted.',
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────
    private function formatAd($b): array
    {
        return [
            'id'             => $b->id,
            'title'          => $b->title,
            'title_ar'       => $b->title_ar,
            'description'    => $b->description,
            'description_ar' => $b->description_ar,
            'type'           => $b->type ?? 'photo',
            'image'          => $b->image,
            'media_url'      => $b->media_url,
            'button_text'    => $b->button_text ?? 'Submit',
            'button_ar'      => $b->button_ar,
            'has_form'       => (bool) $b->has_form,
        ];
    }
}
