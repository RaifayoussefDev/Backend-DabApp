<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="Submission",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="listing_id", type="integer"),
 *     @OA\Property(property="user_id", type="integer"),
 *     @OA\Property(property="amount", type="number", format="float"),
 *     @OA\Property(property="submission_date", type="string", format="date-time"),
 *     @OA\Property(property="status", type="string", enum={"pending", "accepted", "rejected"}),
 *     @OA\Property(property="min_soom", type="number", format="float"),
 *     @OA\Property(
 *         property="user",
 *         type="object",
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="first_name", type="string"),
 *         @OA\Property(property="last_name", type="string"),
 *         @OA\Property(property="email", type="string", format="email")
 *     ),
 *     @OA\Property(
 *         property="listing",
 *         type="object",
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="title", type="string"),
 *         @OA\Property(property="description", type="string")
 *     )
 * )
 */
class SoomController extends Controller
{
    /**
     * Créer une nouvelle soumission SOOM
     * @OA\Post(
     *     path="/api/listings/{listingId}/soom",
     *     summary="Create a new SOOM submission",
     *     tags={"Soom"},
     *     @OA\Parameter(
     *         name="listingId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID of the listing to submit a SOOM for"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="amount", type="number", format="float", description="Amount of the SOOM submission")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="SOOM created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="SOOM created successfully"),
     *             @OA\Property(property="data", type="object", ref="#/components/schemas/Submission"),
     *             @OA\Property(property="previous_highest", type="number", format="float", description="Previous highest SOOM amount")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Listing not found"),
     *     @OA\Response(response=422, description="Validation failed")
     * )
     */
    public function createSoom(Request $request, $listingId)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'message' => 'Unauthorized. User must be logged in.',
                ], 401);
            }

            // Vérifier que le listing existe et permet les soumissions
            $listing = Listing::find($listingId);

            if (!$listing) {
                return response()->json([
                    'message' => 'Listing not found.',
                ], 404);
            }

            if (!$listing->allow_submission) {
                return response()->json([
                    'message' => 'Submissions are not allowed for this listing.',
                ], 403);
            }

            // Vérifier que l'utilisateur n'est pas le vendeur
            if ($listing->seller_id == $userId) {
                return response()->json([
                    'message' => 'Sellers cannot submit SOOMs on their own listings.',
                ], 403);
            }

            // Obtenir la soumission avec le montant le plus élevé (tous statuts confondus)
            $highestSubmission = Submission::where('listing_id', $listingId)
                ->orderBy('amount', 'desc')
                ->first();

            $minAmount = $listing->minimum_bid ?? 0;

            // Si il y a déjà des soumissions, la nouvelle doit être supérieure au plus haut montant
            if ($highestSubmission) {
                $minAmount = $highestSubmission->amount + 1; // Au moins 1 unité de plus
            }

            if ($request->amount < $minAmount) {
                return response()->json([
                    'message' => "The SOOM amount must be at least {$minAmount}.",
                    'minimum_required' => $minAmount,
                    'current_highest' => $highestSubmission ? $highestSubmission->amount : null
                ], 422);
            }

            // Vérifier si l'utilisateur a déjà une soumission PENDING pour ce listing
            $existingUserSubmission = Submission::where('listing_id', $listingId)
                ->where('user_id', $userId)
                ->where('status', 'pending')
                ->first();

            if ($existingUserSubmission) {
                return response()->json([
                    'message' => 'You already have a pending SOOM for this listing. Please wait for the seller to respond or edit your existing SOOM.',
                    'existing_soom_amount' => $existingUserSubmission->amount
                ], 422);
            }

            // Créer la nouvelle soumission avec statut "pending"
            $submission = Submission::create([
                'listing_id' => $listingId,
                'user_id' => $userId,
                'amount' => $request->amount,
                'submission_date' => now(),
                'status' => 'pending',
                'min_soom' => $minAmount,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'SOOM created successfully',
                'data' => $submission->load('user'),
                'previous_highest' => $highestSubmission ? $highestSubmission->amount : null
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to create SOOM',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir toutes les soumissions pour un listing
     * @OA\Get(
     *     path="/api/listings/{listingId}/sooms",
     *     summary="Get all SOOM submissions for a listing",
     *     tags={"Soom"},
     *     @OA\Parameter(
     *         name="listingId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID of the listing to get SOOMs for"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Submissions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Submissions retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Submission")),
     *             @OA\Property(property="total_submissions", type="integer", example=5),
     *             @OA\Property(property="highest_amount", type="number", format="float", example=150.00)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Listing not found")
     * )
     */
    public function getListingSooms($listingId)
    {
        $listing = Listing::find($listingId);

        if (!$listing) {
            return response()->json([
                'message' => 'Listing not found.',
            ], 404);
        }

        $submissions = Submission::where('listing_id', $listingId)
            ->with('user:id,first_name,last_name,email')
            ->orderBy('amount', 'desc')
            ->get();

        return response()->json([
            'message' => 'Submissions retrieved successfully',
            'data' => $submissions,
            'total_submissions' => $submissions->count(),
            'highest_amount' => $submissions->first()?->amount ?? 0
        ]);
    }

    /**
     * Obtenir le montant minimum requis pour un nouveau SOOM
     * @OA\Get(
     *     path="/api/listings/{listingId}/minimum-soom",
     *     summary="Get minimum SOOM amount required for a listing",
     *     tags={"Soom"},
     *     @OA\Parameter(
     *         name="listingId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID of the listing"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Minimum amount retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="minimum_amount", type="number", format="float"),
     *             @OA\Property(property="current_highest", type="number", format="float"),
     *             @OA\Property(property="listing_minimum_bid", type="number", format="float")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Listing not found"),
     *     @OA\Response(response=403, description="Submissions not allowed")
     * )
     */
    public function getMinimumSoomAmount($listingId)
    {
        $listing = Listing::find($listingId);

        if (!$listing) {
            return response()->json([
                'message' => 'Listing not found.',
            ], 404);
        }

        if (!$listing->allow_submission) {
            return response()->json([
                'message' => 'Submissions are not allowed for this listing.',
            ], 403);
        }

        $lastSubmission = Submission::where('listing_id', $listingId)
            ->orderBy('amount', 'desc')
            ->first();

        $minAmount = $listing->minimum_bid ?? 0;

        if ($lastSubmission) {
            $minAmount = $lastSubmission->amount + 1;
        }

        return response()->json([
            'minimum_amount' => $minAmount,
            'current_highest' => $lastSubmission ? $lastSubmission->amount : null,
            'listing_minimum_bid' => $listing->minimum_bid
        ]);
    }

    /**
     * Accepter un SOOM (pour le vendeur)
     * @OA\Patch(
     *     path="/api/submissions/{submissionId}/accept",
     *     summary="Accept a SOOM submission (seller only)",
     *     tags={"Soom"},
     *     @OA\Parameter(
     *         name="submissionId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID of the submission to accept"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SOOM accepted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="SOOM accepted successfully"),
     *             @OA\Property(property="data", type="object", ref="#/components/schemas/Submission")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Only seller can accept"),
     *     @OA\Response(response=404, description="Submission not found")
     * )
     */
    public function acceptSoom($submissionId)
    {
        DB::beginTransaction();

        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'message' => 'Unauthorized. User must be logged in.',
                ], 401);
            }

            $submission = Submission::with('listing')->find($submissionId);

            if (!$submission) {
                return response()->json([
                    'message' => 'Submission not found.',
                ], 404);
            }

            // Vérifier que l'utilisateur est le vendeur du listing
            if ($submission->listing->seller_id != $userId) {
                return response()->json([
                    'message' => 'Only the seller can accept SOOMs for this listing.',
                ], 403);
            }

            // Vérifier que le SOOM n'est pas déjà accepté
            if ($submission->status === 'accepted') {
                return response()->json([
                    'message' => 'This SOOM has already been accepted.',
                ], 422);
            }

            // Accepter ce SOOM
            $submission->update(['status' => 'accepted']);

            // Rejeter tous les autres SOOMs pour ce listing
            Submission::where('listing_id', $submission->listing_id)
                ->where('id', '!=', $submissionId)
                ->where('status', 'pending')
                ->update(['status' => 'rejected']);

            DB::commit();

            return response()->json([
                'message' => 'SOOM accepted successfully',
                'data' => $submission->load(['user:id,first_name,last_name,email', 'listing'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to accept SOOM',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rejeter un SOOM (pour le vendeur)
     * @OA\Patch(
     *     path="/api/submissions/{submissionId}/reject",
     *     summary="Reject a SOOM submission (seller only)",
     *     tags={"Soom"},
     *     @OA\Parameter(
     *         name="submissionId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID of the submission to reject"
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", description="Optional reason for rejection")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SOOM rejected successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="SOOM rejected successfully"),
     *             @OA\Property(property="data", type="object", ref="#/components/schemas/Submission")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Only seller can reject"),
     *     @OA\Response(response=404, description="Submission not found")
     * )
     */
    public function rejectSoom(Request $request, $submissionId)
    {
        DB::beginTransaction();

        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'message' => 'Unauthorized. User must be logged in.',
                ], 401);
            }

            $submission = Submission::with('listing')->find($submissionId);

            if (!$submission) {
                return response()->json([
                    'message' => 'Submission not found.',
                ], 404);
            }

            // Vérifier que l'utilisateur est le vendeur du listing
            if ($submission->listing->seller_id != $userId) {
                return response()->json([
                    'message' => 'Only the seller can reject SOOMs for this listing.',
                ], 403);
            }

            // Vérifier que le SOOM n'est pas déjà rejeté
            if ($submission->status === 'rejected') {
                return response()->json([
                    'message' => 'This SOOM has already been rejected.',
                ], 422);
            }

            // Vérifier que le SOOM n'est pas déjà accepté
            if ($submission->status === 'accepted') {
                return response()->json([
                    'message' => 'Cannot reject an already accepted SOOM.',
                ], 422);
            }

            // Préparer les données de mise à jour
            $updateData = ['status' => 'rejected'];

            // Ajouter la raison de rejet si fournie
            if ($request->has('reason') && !empty($request->reason)) {
                $updateData['rejection_reason'] = $request->reason;
            }

            // Rejeter ce SOOM
            $submission->update($updateData);

            DB::commit();

            return response()->json([
                'message' => 'SOOM rejected successfully',
                'data' => $submission->load(['user:id,first_name,last_name,email', 'listing'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to reject SOOM',
                'details' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Obtenir tous les SOOMs reçus sur mes listings (pour le vendeur)
     * @OA\Get(
     *     path="/api/my-listings-sooms",
     *     summary="Get all SOOMs received on my listings",
     *     tags={"Soom"},
     *     @OA\Response(
     *         response=200,
     *         description="SOOMs retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="SOOMs retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Submission")),
     *             @OA\Property(property="stats", type="object",
     *                 @OA\Property(property="total_sooms", type="integer"),
     *                 @OA\Property(property="pending_sooms", type="integer"),
     *                 @OA\Property(property="accepted_sooms", type="integer"),
     *                 @OA\Property(property="rejected_sooms", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getMyListingsSooms()
    {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'message' => 'Unauthorized. User must be logged in.',
            ], 401);
        }

        // Récupérer tous les SOOMs sur mes listings
        $sooms = Submission::whereHas('listing', function ($query) use ($userId) {
            $query->where('seller_id', $userId);
        })
            ->with([
                'user:id,first_name,last_name,email',
                'listing:id,title,description,seller_id'
            ])
            ->orderBy('submission_date', 'desc')
            ->get();

        // Statistiques
        $stats = [
            'total_sooms' => $sooms->count(),
            'pending_sooms' => $sooms->where('status', 'pending')->count(),
            'accepted_sooms' => $sooms->where('status', 'accepted')->count(),
            'rejected_sooms' => $sooms->where('status', 'rejected')->count(),
        ];

        return response()->json([
            'message' => 'SOOMs retrieved successfully',
            'data' => $sooms,
            'stats' => $stats
        ]);
    }

    /**
     * Obtenir tous mes SOOMs envoyés (pour l'acheteur)
     * @OA\Get(
     *     path="/api/my-sooms",
     *     summary="Get all my submitted SOOMs",
     *     tags={"Soom"},
     *     @OA\Response(
     *         response=200,
     *         description="My SOOMs retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="My SOOMs retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Submission")),
     *             @OA\Property(property="stats", type="object",
     *                 @OA\Property(property="total_sooms", type="integer"),
     *                 @OA\Property(property="pending_sooms", type="integer"),
     *                 @OA\Property(property="accepted_sooms", type="integer"),
     *                 @OA\Property(property="rejected_sooms", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getMySooms()
    {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'message' => 'Unauthorized. User must be logged in.',
            ], 401);
        }

        // Récupérer tous mes SOOMs envoyés
        $sooms = Submission::where('user_id', $userId)
            ->with([
                'listing:id,title,description,seller_id,country_id',
                'listing.seller:id,first_name,last_name,email',
                'listing.country:id,code,name', // Relation avec la table country pour obtenir le code pays du listing
                'user:id,first_name,last_name,email'
            ])
            ->orderBy('submission_date', 'desc')
            ->get();

        // Statistiques
        $stats = [
            'total_sooms' => $sooms->count(),
            'pending_sooms' => $sooms->where('status', 'pending')->count(),
            'accepted_sooms' => $sooms->where('status', 'accepted')->count(),
            'rejected_sooms' => $sooms->where('status', 'rejected')->count(),
        ];

        return response()->json([
            'message' => 'My SOOMs retrieved successfully',
            'data' => $sooms,
            'stats' => $stats
        ]);
    }

    /**
     * Annuler un SOOM (pour l'acheteur)
     * @OA\Delete(
     *     path="/api/submissions/{submissionId}/cancel",
     *     summary="Cancel a SOOM submission (buyer only)",
     *     tags={"Soom"},
     *     @OA\Parameter(
     *         name="submissionId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID of the submission to cancel"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SOOM cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="SOOM cancelled successfully"),
     *             @OA\Property(property="data", type="object", ref="#/components/schemas/Submission")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Only the submitter can cancel or SOOM already processed"),
     *     @OA\Response(response=404, description="Submission not found")
     * )
     */
    public function cancelSoom($submissionId)
    {
        DB::beginTransaction();

        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'message' => 'Unauthorized. User must be logged in.',
                ], 401);
            }

            $submission = Submission::with('listing')->find($submissionId);

            if (!$submission) {
                return response()->json([
                    'message' => 'Submission not found.',
                ], 404);
            }

            // Vérifier que l'utilisateur est celui qui a soumis le SOOM
            if ($submission->user_id != $userId) {
                return response()->json([
                    'message' => 'You can only cancel your own SOOM submissions.',
                ], 403);
            }

            // Vérifier que le SOOM est encore en attente
            if ($submission->status !== 'pending') {
                return response()->json([
                    'message' => 'You can only cancel pending SOOM submissions.',
                    'current_status' => $submission->status
                ], 403);
            }

            // Supprimer le SOOM
            $submission->delete();

            DB::commit();

            return response()->json([
                'message' => 'SOOM cancelled successfully',
                'data' => $submission
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to cancel SOOM',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Modifier un SOOM (pour l'acheteur)
     * @OA\Put(
     *     path="/api/submissions/{submissionId}/edit",
     *     summary="Edit a SOOM submission (buyer only)",
     *     tags={"Soom"},
     *     @OA\Parameter(
     *         name="submissionId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID of the submission to edit"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="amount", type="number", format="float", description="New amount for the SOOM submission")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SOOM updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="SOOM updated successfully"),
     *             @OA\Property(property="data", type="object", ref="#/components/schemas/Submission"),
     *             @OA\Property(property="previous_amount", type="number", format="float", description="Previous SOOM amount"),
     *             @OA\Property(property="current_highest", type="number", format="float", description="Current highest SOOM amount after update")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Only the submitter can edit or SOOM already processed"),
     *     @OA\Response(response=404, description="Submission not found"),
     *     @OA\Response(response=422, description="Validation failed or amount too low")
     * )
     */
    public function editSoom(Request $request, $submissionId)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'message' => 'Unauthorized. User must be logged in.',
                ], 401);
            }

            $submission = Submission::with('listing')->find($submissionId);

            if (!$submission) {
                return response()->json([
                    'message' => 'Submission not found.',
                ], 404);
            }

            // Vérifier que l'utilisateur est celui qui a soumis le SOOM
            if ($submission->user_id != $userId) {
                return response()->json([
                    'message' => 'You can only edit your own SOOM submissions.',
                ], 403);
            }

            // Vérifier que le SOOM est encore en attente
            if ($submission->status !== 'pending') {
                return response()->json([
                    'message' => 'You can only edit pending SOOM submissions.',
                    'current_status' => $submission->status
                ], 403);
            }

            // Vérifier que les soumissions sont toujours autorisées
            if (!$submission->listing->allow_submission) {
                return response()->json([
                    'message' => 'Submissions are no longer allowed for this listing.',
                ], 403);
            }

            $previousAmount = $submission->amount;

            // Obtenir le montant minimum requis (en excluant cette soumission)
            $highestOtherSubmission = Submission::where('listing_id', $submission->listing_id)
                ->where('id', '!=', $submissionId)
                ->orderBy('amount', 'desc')
                ->first();

            $minAmount = $submission->listing->minimum_bid ?? 0;

            // Si il y a d'autres soumissions plus élevées, la nouvelle doit être supérieure
            if ($highestOtherSubmission) {
                $minAmount = max($minAmount, $highestOtherSubmission->amount + 1);
            }

            if ($request->amount < $minAmount) {
                return response()->json([
                    'message' => "The SOOM amount must be at least {$minAmount}.",
                    'minimum_required' => $minAmount,
                    'current_highest_other' => $highestOtherSubmission ? $highestOtherSubmission->amount : null,
                    'your_current_amount' => $previousAmount
                ], 422);
            }

            // Mettre à jour la soumission
            $submission->update([
                'amount' => $request->amount,
                'submission_date' => now(), // Mettre à jour la date de soumission
                'min_soom' => $minAmount,
            ]);

            // Obtenir le montant le plus élevé actuel pour la réponse
            $currentHighest = Submission::where('listing_id', $submission->listing_id)
                ->orderBy('amount', 'desc')
                ->first();

            DB::commit();

            return response()->json([
                'message' => 'SOOM updated successfully',
                'data' => $submission->load('user'),
                'previous_amount' => $previousAmount,
                'current_highest' => $currentHighest->amount
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to update SOOM',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir la dernière soumission SOOM pour un listing
     * @OA\Get(
     *     path="/api/listings/{listingId}/last-soom",
     *     summary="Get the last SOOM submission for a listing",
     *     tags={"Soom"},
     *     @OA\Parameter(
     *         name="listingId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID of the listing to get the last SOOM for"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SOOM data retrieved successfully",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="Last SOOM retrieved successfully"),
     *                     @OA\Property(property="data", type="object", ref="#/components/schemas/Submission"),
     *                     @OA\Property(property="has_sooms", type="boolean", example=true),
     *                     @OA\Property(property="total_sooms_count", type="integer", example=5),
     *                     @OA\Property(property="minimum_bid_required", type="number", format="float")
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="No SOOMs found for this listing"),
     *                     @OA\Property(property="data", type="null"),
     *                     @OA\Property(property="has_sooms", type="boolean", example=false),
     *                     @OA\Property(property="total_sooms_count", type="integer", example=0),
     *                     @OA\Property(property="minimum_bid_required", type="number", format="float"),
     *                     @OA\Property(property="listing_minimum_bid", type="number", format="float")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(response=404, description="Listing not found")
     * )
     */
    public function getLastSoom($listingId)
    {
        // Vérifier que le listing existe et récupérer ses informations
        $listing = Listing::select('id', 'title', 'description', 'seller_id', 'minimum_bid')
            ->find($listingId);

        if (!$listing) {
            return response()->json([
                'message' => 'Listing not found.',
            ], 404);
        }

        // Obtenir le nombre total de SOOMs pour ce listing
        $totalSoomsCount = Submission::where('listing_id', $listingId)->count();

        // Obtenir la dernière soumission (la plus récente par date)
        $lastSubmission = Submission::where('listing_id', $listingId)
            ->with([
                'user:id,first_name,last_name,email',
                'listing:id,title,description,seller_id'
            ])
            ->orderBy('submission_date', 'desc')
            ->first();

        if (!$lastSubmission) {
            // Aucun SOOM trouvé - retourner le minimum_bid du listing
            $minimumBidRequired = $listing->minimum_bid ?? 0;

            return response()->json([
                'message' => 'No SOOMs found for this listing',
                'data' => null,
                'has_sooms' => false,
                'total_sooms_count' => 0,
                'minimum_bid_required' => (float) $minimumBidRequired,
                'listing_minimum_bid' => (float) ($listing->minimum_bid ?? 0)
            ], 200);
        }

        // Formater les montants comme float
        $lastSubmission->amount = (float) $lastSubmission->amount;
        $lastSubmission->min_soom = (float) $lastSubmission->min_soom;

        // Calculer le montant minimum requis pour la prochaine soumission
        $minimumBidRequired = $lastSubmission->amount + 1;

        return response()->json([
            'message' => 'Last SOOM retrieved successfully',
            'data' => $lastSubmission,
            'has_sooms' => true,
            'total_sooms_count' => $totalSoomsCount,
            'minimum_bid_required' => (float) $minimumBidRequired
        ], 200);
    }
}
