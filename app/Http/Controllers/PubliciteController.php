<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\PubliciteSubmission;
use App\Services\GoogleSheetsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PubliciteController extends Controller
{
    public function __construct(private GoogleSheetsService $sheetsService) {}

    /**
     * @OA\Get(
     *     path="/api/publicites",
     *     tags={"Publicités"},
     *     summary="Liste des publicités actives (mobile app)",
     *     description="Retourne toutes les publicités actives avec formulaire (has_form=true) dans la fenêtre de dates.",
     *     @OA\Response(
     *         response=200,
     *         description="Liste des publicités",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id",          type="integer", example=1),
     *                     @OA\Property(property="title",       type="string",  example="Offre Ramadan 2026"),
     *                     @OA\Property(property="description", type="string",  example="Profitez de -30% pendant le Ramadan"),
     *                     @OA\Property(property="type",        type="string",  enum={"photo","video"}, example="video"),
     *                     @OA\Property(property="image",       type="string",  example="https://cdn.dabapp.com/ads/ramadan.jpg"),
     *                     @OA\Property(property="media_url",   type="string",  example="https://cdn.dabapp.com/ads/ramadan.mp4"),
     *                     @OA\Property(property="button_text", type="string",  example="Je suis intéressé"),
     *                     @OA\Property(property="has_form",    type="boolean", example=true)
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
     *     path="/api/publicites/{id}",
     *     tags={"Publicités"},
     *     summary="Détail d'une publicité",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), example=1),
     *     @OA\Response(
     *         response=200,
     *         description="Détail de la publicité",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",          type="integer", example=1),
     *                 @OA\Property(property="title",       type="string",  example="Offre Ramadan 2026"),
     *                 @OA\Property(property="type",        type="string",  enum={"photo","video"}, example="video"),
     *                 @OA\Property(property="media_url",   type="string",  example="https://cdn.dabapp.com/ads/ramadan.mp4"),
     *                 @OA\Property(property="button_text", type="string",  example="Je suis intéressé"),
     *                 @OA\Property(property="has_form",    type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Publicité non trouvée")
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
     *     path="/api/publicites/{id}/submit",
     *     tags={"Publicités"},
     *     summary="Soumettre le formulaire de contact d'une publicité",
     *     description="L'utilisateur remplit nom, prénom, téléphone, ville après avoir vu la pub. Résultat sauvegardé en BD et synchronisé dans Google Sheets.",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), example=1),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nom","prenom","phone","city_id"},
     *             @OA\Property(property="nom",     type="string",  example="Benali"),
     *             @OA\Property(property="prenom",  type="string",  example="Ahmed"),
     *             @OA\Property(property="phone",   type="string",  example="0551234567"),
     *             @OA\Property(property="city_id", type="integer", example=1, description="ID depuis la table cities")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Soumission enregistrée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Thank you! Your information has been submitted.")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Publicité non trouvée ou inactive"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function submit(Request $request, $id)
    {
        $ad = Banner::where('is_active', true)->where('has_form', true)->find($id);

        if (!$ad) {
            return response()->json(['success' => false, 'message' => 'Ad not found or inactive'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom'     => 'required|string|max:100',
            'prenom'  => 'required|string|max:100',
            'phone'   => 'required|string|max:20',
            'city_id' => 'required|integer|exists:cities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $submission = PubliciteSubmission::create([
            'banner_id' => $ad->id,
            'user_id'   => auth('api')->id(),
            'nom'       => $request->nom,
            'prenom'    => $request->prenom,
            'phone'     => $request->phone,
            'city_id'   => $request->city_id,
        ]);

        if ($ad->google_sheet_id) {
            $cityName = $submission->city?->name ?? $request->city_id;

            $synced = $this->sheetsService->appendRow(
                $ad->google_sheet_id,
                [
                    $submission->id,
                    $submission->nom,
                    $submission->prenom,
                    $submission->phone,
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
            'id'          => $b->id,
            'title'       => $b->title,
            'description' => $b->description,
            'type'        => $b->type ?? 'photo',
            'image'       => $b->image,
            'media_url'   => $b->media_url,
            'button_text' => $b->button_text ?? 'Submit',
            'has_form'    => (bool) $b->has_form,
        ];
    }
}
