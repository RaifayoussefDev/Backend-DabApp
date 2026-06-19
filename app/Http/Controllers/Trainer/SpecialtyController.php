<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

/**
 * @OA\Tag(
 *     name="Specialties",
 *     description="Dynamic trainer specialties — browse publicly, manage via admin"
 * )
 */
class SpecialtyController extends Controller
{
    // ---------------------------------------------------------------
    // PUBLIC — List all active specialties
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/specialties",
     *     summary="List all active specialties",
     *     description="Returns all active specialties ordered by sort_order. Use for building filter chips and dropdown selectors.",
     *     operationId="listSpecialties",
     *     tags={"Specialties"},
     *     @OA\Parameter(name="with_count", in="query", required=false, @OA\Schema(type="integer", enum={0,1}),
     *         description="If 1, include the number of approved trainers per specialty"),
     *     @OA\Response(
     *         response=200,
     *         description="Specialties retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id",             type="integer", example=1),
     *                     @OA\Property(property="libelle_en",     type="string",  example="Coaching"),
     *                     @OA\Property(property="libelle_ar",     type="string",  example="تدريب"),
     *                     @OA\Property(property="slug",           type="string",  example="coaching"),
     *                     @OA\Property(property="icon_url",       type="string",  example="https://api.dabapp.com/storage/icons/coaching.svg"),
     *                     @OA\Property(property="localized_label",type="string",  example="Coaching",
     *                         description="Automatically returns EN or AR based on Accept-Language header"),
     *                     @OA\Property(property="trainers_count", type="integer", example=12,
     *                         description="Only present when with_count=1")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Specialty::active();

        if ($request->boolean('with_count')) {
            $query->withCount(['trainers' => fn ($q) => $q->where('status', 'approved')]);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->get(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/specialties/{id}",
     *     summary="Get specialty detail",
     *     operationId="showSpecialty",
     *     tags={"Specialties"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Specialty found"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(int $id)
    {
        $specialty = Specialty::active()->find($id);
        if (!$specialty) {
            return response()->json(['success' => false, 'message' => 'Specialty not found'], 404);
        }
        return response()->json(['success' => true, 'data' => $specialty]);
    }

    // ---------------------------------------------------------------
    // ADMIN — CRUD
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/admin/specialties",
     *     summary="Admin: list all specialties",
     *     operationId="adminListSpecialties",
     *     tags={"Admin — Specialties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Specialties list (including inactive)")
     * )
     */
    public function adminIndex()
    {
        $specialties = Specialty::orderBy('sort_order')->withCount('trainers')->get();
        return response()->json(['success' => true, 'data' => $specialties]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/specialties",
     *     summary="Admin: create specialty",
     *     description="Create a new specialty. Upload the icon separately via POST /api/admin/specialties/{id}/icon or include icon file in this request.",
     *     operationId="adminCreateSpecialty",
     *     tags={"Admin — Specialties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"libelle_en","libelle_ar"},
     *                 @OA\Property(property="libelle_en",  type="string",  example="Coaching"),
     *                 @OA\Property(property="libelle_ar",  type="string",  example="تدريب"),
     *                 @OA\Property(property="slug",        type="string",  example="coaching"),
     *                 @OA\Property(property="sort_order",  type="integer", example=0),
     *                 @OA\Property(property="is_active",   type="integer", enum={0,1}, example=1),
     *                 @OA\Property(property="icon",        type="string",  format="binary",
     *                     description="SVG, PNG or WebP icon (max 2MB)")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Specialty created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'libelle_en' => 'required|string|max:100',
            'libelle_ar' => 'required|string|max:100',
            'slug'       => 'nullable|string|max:100|unique:specialties,slug',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'nullable|boolean',
            'icon'       => 'nullable|mimes:jpeg,jpg,png,webp,svg|max:2048',
        ]);

        if ($request->hasFile('icon')) {
            $validated['icon'] = $this->saveIcon($request->file('icon'));
        }

        $specialty = Specialty::create($validated);

        return response()->json(['success' => true, 'message' => 'Specialty created', 'data' => $specialty], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/specialties/{id}",
     *     summary="Admin: update specialty",
     *     operationId="adminUpdateSpecialty",
     *     tags={"Admin — Specialties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="libelle_en",  type="string"),
     *                 @OA\Property(property="libelle_ar",  type="string"),
     *                 @OA\Property(property="sort_order",  type="integer"),
     *                 @OA\Property(property="is_active",   type="integer", enum={0,1}),
     *                 @OA\Property(property="icon",        type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, int $id)
    {
        $specialty = Specialty::find($id);
        if (!$specialty) {
            return response()->json(['success' => false, 'message' => 'Specialty not found'], 404);
        }

        $validated = $request->validate([
            'libelle_en' => 'nullable|string|max:100',
            'libelle_ar' => 'nullable|string|max:100',
            'slug'       => 'nullable|string|max:100|unique:specialties,slug,' . $id,
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'nullable|boolean',
            'icon'       => 'nullable|mimes:jpeg,jpg,png,webp,svg|max:2048',
        ]);

        if ($request->hasFile('icon')) {
            if ($specialty->icon) Storage::disk('public')->delete($specialty->icon);
            $validated['icon'] = $this->saveIcon($request->file('icon'));
        }

        $specialty->update(array_filter($validated, fn ($v) => $v !== null));

        return response()->json(['success' => true, 'message' => 'Specialty updated', 'data' => $specialty->fresh()]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/specialties/{id}",
     *     summary="Admin: delete specialty",
     *     operationId="adminDeleteSpecialty",
     *     tags={"Admin — Specialties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(int $id)
    {
        $specialty = Specialty::find($id);
        if (!$specialty) {
            return response()->json(['success' => false, 'message' => 'Specialty not found'], 404);
        }

        if ($specialty->icon) Storage::disk('public')->delete($specialty->icon);
        $specialty->delete();

        return response()->json(['success' => true, 'message' => 'Specialty deleted']);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/specialties/{id}/icon",
     *     summary="Admin: upload/replace specialty icon",
     *     description="Upload or replace the icon for a specialty. Accepts SVG, PNG, WebP, JPEG (max 2MB).",
     *     operationId="uploadSpecialtyIcon",
     *     tags={"Admin — Specialties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"icon"},
     *                 @OA\Property(property="icon", type="string", format="binary",
     *                     description="SVG, PNG, WebP, or JPEG — max 2MB")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Icon uploaded",
     *         @OA\JsonContent(
     *             @OA\Property(property="success",  type="boolean", example=true),
     *             @OA\Property(property="icon_url", type="string",  example="https://api.dabapp.com/storage/icons/coaching.svg")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Specialty not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function uploadIcon(Request $request, int $id)
    {
        $specialty = Specialty::find($id);
        if (!$specialty) {
            return response()->json(['success' => false, 'message' => 'Specialty not found'], 404);
        }

        $request->validate([
            'icon' => 'required|mimes:jpeg,jpg,png,webp,svg|max:2048',
        ]);

        if ($specialty->icon) Storage::disk('public')->delete($specialty->icon);
        $path = $this->saveIcon($request->file('icon'));
        $specialty->update(['icon' => $path]);

        return response()->json([
            'success'  => true,
            'message'  => 'Icon uploaded successfully',
            'icon_url' => $specialty->icon_url,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/specialties/reorder",
     *     summary="Admin: reorder specialties",
     *     operationId="reorderSpecialties",
     *     tags={"Admin — Specialties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"order"},
     *             @OA\Property(property="order", type="array", @OA\Items(type="integer"), example={3,1,2,4})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Order updated")
     * )
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'order'   => 'required|array',
            'order.*' => 'integer|exists:specialties,id',
        ]);

        foreach ($request->order as $index => $specId) {
            Specialty::where('id', $specId)->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true, 'message' => 'Specialties reordered']);
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function saveIcon($file): string
    {
        $ext      = strtolower($file->getClientOriginalExtension());
        $filename = 'specialty_' . Str::random(16) . '.' . $ext;

        if ($ext === 'svg') {
            Storage::disk('public')->putFileAs('icons/specialties', $file, $filename);
        } else {
            $manager = new ImageManager(new Driver());
            $img     = $manager->read($file->getRealPath());
            $img->scaleDown(128, 128);
            Storage::disk('public')->put("icons/specialties/{$filename}", (string) $img->toPng());
            $filename = str_replace($ext, 'png', $filename);
        }

        return "icons/specialties/{$filename}";
    }
}
