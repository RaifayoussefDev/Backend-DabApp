<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\CommissionHistory;
use App\Models\CommissionSetting;
use App\Models\Trainer;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Admin - Commission",
 *     description="Manage DabApp commission rates for trainer bookings"
 * )
 */
class AdminCommissionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/commission",
     *     summary="Get current commission settings",
     *     description="Returns the active global commission rate and any trainer-specific overrides.",
     *     operationId="adminGetCommission",
     *     tags={"Admin - Commission"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Commission settings retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="global_rate", type="object",
     *                     @OA\Property(property="id",                    type="integer", example=1),
     *                     @OA\Property(property="commission_percentage", type="number",  format="float", example=20.00),
     *                     @OA\Property(property="effective_from",        type="string",  format="date"),
     *                     @OA\Property(property="effective_until",       type="string",  format="date", nullable=true),
     *                     @OA\Property(property="notes",                 type="string",  nullable=true)
     *                 ),
     *                 @OA\Property(property="trainer_overrides", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",                    type="integer"),
     *                         @OA\Property(property="trainer_id",            type="integer"),
     *                         @OA\Property(property="trainer_name",          type="string"),
     *                         @OA\Property(property="commission_percentage", type="number", format="float"),
     *                         @OA\Property(property="effective_from",        type="string", format="date"),
     *                         @OA\Property(property="effective_until",       type="string", format="date", nullable=true)
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $global = CommissionSetting::active()->global()->latest()->first();

        $overrides = CommissionSetting::active()
            ->where('entity_type', 'trainer')
            ->with('trainer:id,name')
            ->get()
            ->map(fn ($s) => [
                'id'                    => $s->id,
                'trainer_id'            => $s->entity_id,
                'trainer_name'          => $s->trainer->name ?? 'Unknown',
                'commission_percentage' => $s->commission_percentage,
                'effective_from'        => $s->effective_from,
                'effective_until'       => $s->effective_until,
                'notes'                 => $s->notes,
            ]);

        return response()->json([
            'success' => true,
            'data'    => ['global_rate' => $global, 'trainer_overrides' => $overrides],
            'message' => 'Commission settings retrieved',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/commission/global",
     *     summary="Set global commission rate",
     *     description="Creates or updates the global commission rate. The change is logged in commission_history. All new bookings after effective_from will use this rate. Existing confirmed bookings are not affected.",
     *     operationId="adminSetGlobalCommission",
     *     tags={"Admin - Commission"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"commission_percentage","effective_from"},
     *             @OA\Property(property="commission_percentage", type="number", format="float", example=20.00,
     *                 description="DabApp commission percentage (0–100)"),
     *             @OA\Property(property="effective_from",        type="string", format="date", example="2026-06-15"),
     *             @OA\Property(property="effective_until",       type="string", format="date", example=null, nullable=true),
     *             @OA\Property(property="notes",                 type="string", example="New rate approved in board meeting 2026-06-12")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Global rate set",
     *         @OA\JsonContent(
     *             @OA\Property(property="success",               type="boolean", example=true),
     *             @OA\Property(property="message",               type="string",  example="Global commission rate updated to 20%"),
     *             @OA\Property(property="commission_percentage", type="number",  format="float", example=20.00)
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function setGlobal(Request $request)
    {
        $admin = JWTAuth::parseToken()->authenticate();

        $validated = $request->validate([
            'commission_percentage' => 'required|numeric|min:0|max:100',
            'effective_from'        => 'required|date',
            'effective_until'       => 'nullable|date|after:effective_from',
            'notes'                 => 'nullable|string|max:1000',
        ]);

        // Deactivate previous global setting
        $previous = CommissionSetting::active()->global()->first();
        $oldPct   = $previous ? $previous->commission_percentage : null;

        if ($previous) {
            $previous->update(['is_active' => false]);
        }

        $newSetting = CommissionSetting::create([
            'entity_type'           => 'global',
            'entity_id'             => null,
            'commission_percentage' => $validated['commission_percentage'],
            'is_active'             => true,
            'effective_from'        => $validated['effective_from'],
            'effective_until'       => $validated['effective_until'] ?? null,
            'notes'                 => $validated['notes'] ?? null,
            'created_by'            => $admin->id,
        ]);

        // Log the change
        if ($previous) {
            CommissionHistory::create([
                'commission_setting_id' => $newSetting->id,
                'changed_by'            => $admin->id,
                'old_percentage'        => $oldPct,
                'new_percentage'        => $validated['commission_percentage'],
                'reason'                => $validated['notes'] ?? null,
                'changed_at'            => now(),
            ]);
        }

        return response()->json([
            'success'               => true,
            'message'               => "Global commission rate updated to {$validated['commission_percentage']}%",
            'commission_percentage' => $validated['commission_percentage'],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/commission/trainer/{trainerId}",
     *     summary="Set trainer-specific commission rate",
     *     description="Creates a commission override for a specific trainer. This takes priority over the global rate.",
     *     operationId="adminSetTrainerCommission",
     *     tags={"Admin - Commission"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="trainerId", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"commission_percentage","effective_from"},
     *             @OA\Property(property="commission_percentage", type="number", format="float", example=15.00,
     *                 description="Override rate for this trainer specifically"),
     *             @OA\Property(property="effective_from",  type="string", format="date", example="2026-06-15"),
     *             @OA\Property(property="effective_until", type="string", format="date", nullable=true),
     *             @OA\Property(property="notes",           type="string", example="Special agreement with this trainer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Trainer rate set"),
     *     @OA\Response(response=404, description="Trainer not found")
     * )
     */
    public function setTrainerRate(Request $request, int $trainerId)
    {
        $admin   = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::find($trainerId);

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        $validated = $request->validate([
            'commission_percentage' => 'required|numeric|min:0|max:100',
            'effective_from'        => 'required|date',
            'effective_until'       => 'nullable|date|after:effective_from',
            'notes'                 => 'nullable|string|max:1000',
        ]);

        // Deactivate previous trainer-specific setting
        CommissionSetting::active()->forTrainer($trainerId)->update(['is_active' => false]);

        CommissionSetting::create([
            'entity_type'           => 'trainer',
            'entity_id'             => $trainerId,
            'commission_percentage' => $validated['commission_percentage'],
            'is_active'             => true,
            'effective_from'        => $validated['effective_from'],
            'effective_until'       => $validated['effective_until'] ?? null,
            'notes'                 => $validated['notes'] ?? null,
            'created_by'            => $admin->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Commission override set to {$validated['commission_percentage']}% for trainer {$trainer->name}",
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/commission/history",
     *     summary="Commission change history",
     *     description="Returns the full history of commission rate changes.",
     *     operationId="adminCommissionHistory",
     *     tags={"Admin - Commission"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="History retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",             type="integer"),
     *                         @OA\Property(property="old_percentage", type="number", format="float", example=15.00),
     *                         @OA\Property(property="new_percentage", type="number", format="float", example=20.00),
     *                         @OA\Property(property="reason",         type="string"),
     *                         @OA\Property(property="changed_at",     type="string", format="datetime"),
     *                         @OA\Property(property="changed_by", type="object",
     *                             @OA\Property(property="id",         type="integer"),
     *                             @OA\Property(property="first_name", type="string"),
     *                             @OA\Property(property="last_name",  type="string")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function history(Request $request)
    {
        $history = CommissionHistory::with('changedBy:id,first_name,last_name')
            ->latest('changed_at')
            ->paginate($request->get('per_page', 20));

        return response()->json(['success' => true, 'data' => $history, 'message' => 'Commission history retrieved']);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/commission/trainer/{trainerId}",
     *     summary="Remove trainer commission override",
     *     description="Deactivates the trainer-specific commission override. The trainer will fall back to the global rate for all future bookings.",
     *     operationId="adminRemoveTrainerCommission",
     *     tags={"Admin - Commission"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="trainerId", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Override removed — trainer now uses global rate",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Trainer commission override removed — will use global rate")
     *         )
     *     ),
     *     @OA\Response(response=404, description="No active override found for this trainer")
     * )
     */
    public function removeTrainerRate(int $trainerId)
    {
        $setting = CommissionSetting::active()->forTrainer($trainerId)->first();

        if (!$setting) {
            return response()->json(['success' => false, 'message' => 'No active override found for this trainer'], 404);
        }

        $setting->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Trainer commission override removed — will use global rate',
        ]);
    }
}
