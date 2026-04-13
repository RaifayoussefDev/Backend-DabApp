<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\PubliciteSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminPubliciteController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/publicites",
     *     tags={"Admin – Publicités"},
     *     summary="Liste de toutes les publicités (admin)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", example=15)),
     *     @OA\Parameter(name="search",   in="query", @OA\Schema(type="string"),  description="Recherche par titre"),
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginée des publicités",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id",               type="integer", example=1),
     *                         @OA\Property(property="title",            type="string",  example="Offre Ramadan 2026"),
     *                         @OA\Property(property="type",             type="string",  enum={"photo","video"}, example="video"),
     *                         @OA\Property(property="google_sheet_id",  type="string",  example="15ln515Ecn1lw1ZdHlqe3BdDuXzlxskZLmfdpa7wjXNU"),
     *                         @OA\Property(property="is_active",        type="boolean", example=true),
     *                         @OA\Property(property="submissions_count",type="integer", example=42)
     *                     )
     *                 ),
     *                 @OA\Property(property="total",        type="integer", example=5),
     *                 @OA\Property(property="current_page", type="integer", example=1)
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
            $query->where('title', 'like', "%{$search}%");
        }

        $ads = $query->withCount('submissions')->paginate($perPage);

        $ads->getCollection()->transform(fn ($b) => $this->formatAdAdmin($b));

        return response()->json(['success' => true, 'data' => $ads]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/publicites",
     *     tags={"Admin – Publicités"},
     *     summary="Créer une nouvelle publicité",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title"},
     *             @OA\Property(property="title",           type="string",  example="Offre Ramadan 2026"),
     *             @OA\Property(property="description",     type="string",  example="Profitez de -30% pendant le Ramadan"),
     *             @OA\Property(property="type",            type="string",  enum={"photo","video"}, example="video"),
     *             @OA\Property(property="image",           type="string",  example="https://cdn.dabapp.com/ads/ramadan-thumb.jpg"),
     *             @OA\Property(property="media_url",       type="string",  example="https://cdn.dabapp.com/ads/ramadan.mp4"),
     *             @OA\Property(property="button_text",     type="string",  example="Je suis intéressé"),
     *             @OA\Property(property="google_sheet_id", type="string",  example="15ln515Ecn1lw1ZdHlqe3BdDuXzlxskZLmfdpa7wjXNU"),
     *             @OA\Property(property="order",           type="integer", example=1),
     *             @OA\Property(property="is_active",       type="boolean", example=true),
     *             @OA\Property(property="start_date",      type="string",  format="date", example="2026-04-14"),
     *             @OA\Property(property="end_date",        type="string",  format="date", example="2026-05-14")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Publicité créée",
     *         @OA\JsonContent(@OA\Property(property="success", type="boolean", example=true),
     *                         @OA\Property(property="message", type="string",  example="Ad created successfully"))
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'type'            => 'nullable|in:photo,video',
            'image'           => 'nullable|string',
            'media_url'       => 'nullable|string',
            'button_text'     => 'nullable|string|max:100',
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
            'description'     => $request->description,
            'type'            => $request->type ?? 'photo',
            'image'           => $request->image,
            'media_url'       => $request->media_url,
            'button_text'     => $request->button_text,
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
            'data'    => $this->formatAdAdmin($ad),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/publicites/{id}",
     *     tags={"Admin – Publicités"},
     *     summary="Détail d'une publicité",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), example=1),
     *     @OA\Response(response=200, description="Détail de la publicité avec nombre de soumissions"),
     *     @OA\Response(response=404, description="Non trouvée")
     * )
     */
    public function show($id)
    {
        $ad = Banner::where('has_form', true)->withCount('submissions')->find($id);

        if (!$ad) {
            return response()->json(['success' => false, 'message' => 'Ad not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->formatAdAdmin($ad)]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/publicites/{id}",
     *     tags={"Admin – Publicités"},
     *     summary="Modifier une publicité",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), example=1),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="title",           type="string",  example="Offre Modifiée"),
     *             @OA\Property(property="type",            type="string",  enum={"photo","video"}),
     *             @OA\Property(property="google_sheet_id", type="string",  example="15ln515Ecn1lw1ZdHlqe3BdDuXzlxskZLmfdpa7wjXNU"),
     *             @OA\Property(property="is_active",       type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Publicité mise à jour"),
     *     @OA\Response(response=404, description="Non trouvée")
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
            'description'     => 'nullable|string',
            'type'            => 'nullable|in:photo,video',
            'image'           => 'nullable|string',
            'media_url'       => 'nullable|string',
            'button_text'     => 'nullable|string|max:100',
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
            'title', 'description', 'type', 'image', 'media_url',
            'button_text', 'google_sheet_id', 'link',
            'order', 'is_active', 'start_date', 'end_date',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Ad updated successfully',
            'data'    => $this->formatAdAdmin($ad),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/publicites/{id}",
     *     tags={"Admin – Publicités"},
     *     summary="Supprimer une publicité",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), example=1),
     *     @OA\Response(response=200, description="Supprimée"),
     *     @OA\Response(response=404, description="Non trouvée")
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
     *     path="/api/admin/publicites/{id}/toggle",
     *     tags={"Admin – Publicités"},
     *     summary="Activer / désactiver une publicité",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), example=1),
     *     @OA\Response(
     *         response=200,
     *         description="Statut mis à jour",
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
     *     path="/api/admin/publicites/{id}/submissions",
     *     tags={"Admin – Publicités"},
     *     summary="Soumissions d'une publicité (leads)",
     *     description="Liste les utilisateurs qui ont soumis le formulaire après avoir vu la publicité. Filtrable par nom, prénom, téléphone.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id",       in="path",  required=true, @OA\Schema(type="integer"), example=1),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", example=25)),
     *     @OA\Parameter(name="search",   in="query", @OA\Schema(type="string"),  description="Recherche par nom, prénom ou téléphone"),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des soumissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="ad", type="object",
     *                 @OA\Property(property="id",    type="integer", example=1),
     *                 @OA\Property(property="title", type="string",  example="Offre Ramadan 2026")
     *             ),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id",              type="integer", example=1),
     *                         @OA\Property(property="user_id",         type="integer", example=5,    nullable=true),
     *                         @OA\Property(property="user_name",       type="string",  example="Mohammed Ali", nullable=true),
     *                         @OA\Property(property="nom",             type="string",  example="Benali"),
     *                         @OA\Property(property="prenom",          type="string",  example="Ahmed"),
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
     *     @OA\Response(response=404, description="Publicité non trouvée")
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

        $query = PubliciteSubmission::where('banner_id', $id)->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nom',    'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('phone',  'like', "%{$search}%");
            });
        }

        $submissions = $query->with(['city', 'user'])->paginate($perPage);

        $submissions->getCollection()->transform(fn ($s) => [
            'id'              => $s->id,
            'user_id'         => $s->user_id,
            'user_name'       => $s->user?->name,
            'nom'             => $s->nom,
            'prenom'          => $s->prenom,
            'phone'           => $s->phone,
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
    private function formatAdAdmin(Banner $b): array
    {
        return [
            'id'                => $b->id,
            'title'             => $b->title,
            'description'       => $b->description,
            'type'              => $b->type ?? 'photo',
            'image'             => $b->image,
            'media_url'         => $b->media_url,
            'button_text'       => $b->button_text,
            'google_sheet_id'   => $b->google_sheet_id,
            'order'             => $b->order,
            'is_active'         => (bool) $b->is_active,
            'start_date'        => $b->start_date ? (string) $b->start_date : null,
            'end_date'          => $b->end_date   ? (string) $b->end_date   : null,
            'submissions_count' => $b->submissions_count ?? null,
            'created_at'        => $b->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
