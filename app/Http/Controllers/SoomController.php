<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Submission;
use App\Models\AuctionHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\SoomCreatedMail;
use App\Mail\SoomAcceptedMail;
use App\Mail\SoomRejectedMail;
use App\Mail\SaleValidatedMail;
use Carbon\Carbon;


class SoomController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/listings/{listingId}/soom",
     *     summary="Create a new SOOM (Submission of Offer on Market)",
     *     description="Create a new SOOM for a specific listing. Users can submit multiple SOOMs, but each must be higher than the previous highest amount.",
     *     operationId="createSoom",
     *     tags={"SOOMs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="listingId",
     *         in="path",
     *         description="ID of the listing to submit SOOM for",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="SOOM data",
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(
     *                 property="amount",
     *                 type="number",
     *                 format="float",
     *                 description="Amount of the SOOM offer",
     *                 example=1500.00,
     *                 minimum=0
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="SOOM created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="SOOM created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="listing_id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=2),
     *                 @OA\Property(property="amount", type="number", format="float", example=1500.00),
     *                 @OA\Property(property="submission_date", type="string", format="datetime", example="2024-09-18T16:30:00.000000Z"),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="min_soom", type="number", format="float", example=1400.00),
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com")
     *                 )
     *             ),
     *             @OA\Property(property="previous_highest", type="number", format="float", example=1400.00, nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - User must be logged in",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized. User must be logged in.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Various authorization issues",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string",
     *                 example="Submissions are not allowed for this listing.",
     *                 enum={"Submissions are not allowed for this listing.", "Sellers cannot submit SOOMs on their own listings."}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Listing not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Listing not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or amount too low",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="Validation failed"),
     *                     @OA\Property(
     *                         property="errors",
     *                         type="object",
     *                         @OA\Property(
     *                             property="amount",
     *                             type="array",
     *                             @OA\Items(type="string", example="The amount field is required.")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="The SOOM amount must be at least 1501."),
     *                     @OA\Property(property="minimum_required", type="number", format="float", example=1501.00),
     *                     @OA\Property(property="current_highest", type="number", format="float", example=1500.00, nullable=true)
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to create SOOM"),
     *             @OA\Property(property="details", type="string", example="Database connection failed")
     *         )
     *     )
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
            $listing = Listing::with('seller')->find($listingId);

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
                $minAmount = $highestSubmission->amount + 1;
            }

            if ($request->amount < $minAmount) {
                return response()->json([
                    'message' => "The SOOM amount must be at least {$minAmount}.",
                    'minimum_required' => $minAmount,
                    'current_highest' => $highestSubmission ? $highestSubmission->amount : null
                ], 422);
            }

            // SUPPRIMÉ: La vérification du SOOM pending existant
            // Maintenant l'utilisateur peut faire plusieurs SOOMs

            // Créer la nouvelle soumission avec statut "pending"
            $submission = Submission::create([
                'listing_id' => $listingId,
                'user_id' => $userId,
                'amount' => $request->amount,
                'submission_date' => now(),
                'status' => 'pending',
                'min_soom' => $minAmount,
            ]);

            // Envoyer notification email au vendeur
            $buyer = Auth::user();
            try {
                Mail::to($listing->seller->email)->send(new SoomCreatedMail($submission, $listing, $buyer));
            } catch (\Exception $e) {
                \Log::error('Failed to send SOOM created email: ' . $e->getMessage());
            }

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
     * @OA\Get(
     *     path="/api/listings/{listingId}/sooms",
     *     summary="Get all SOOMs for a specific listing",
     *     description="Retrieve all SOOM submissions for a specific listing, ordered by amount (highest first)",
     *     operationId="getListingSooms",
     *     tags={"SOOMs"},
     *     @OA\Parameter(
     *         name="listingId",
     *         in="path",
     *         description="ID of the listing to get SOOMs for",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Submissions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Submissions retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="listing_id", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=2),
     *                     @OA\Property(property="amount", type="number", format="float", example=1500.00),
     *                     @OA\Property(property="submission_date", type="string", format="datetime", example="2024-09-18T16:30:00.000000Z"),
     *                     @OA\Property(property="status", type="string", example="pending", enum={"pending", "accepted", "rejected"}),
     *                     @OA\Property(property="min_soom", type="number", format="float", example=1400.00),
     *                     @OA\Property(property="created_at", type="string", format="datetime", example="2024-09-18T16:30:00.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="datetime", example="2024-09-18T16:30:00.000000Z"),
     *                     @OA\Property(
     *                         property="user",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="first_name", type="string", example="John"),
     *                         @OA\Property(property="last_name", type="string", example="Doe"),
     *                         @OA\Property(property="email", type="string", example="john@example.com")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="total_submissions", type="integer", example=3),
     *             @OA\Property(property="highest_amount", type="number", format="float", example=1500.00)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Listing not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Listing not found.")
     *         )
     *     )
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
     * @OA\Get(
     *     path="/api/listings/{listingId}/minimum-soom",
     *     summary="Get minimum SOOM amount for a listing",
     *     description="Get the minimum amount required for a new SOOM submission based on current highest bid",
     *     operationId="getMinimumSoomAmount",
     *     tags={"SOOMs"},
     *     @OA\Parameter(
     *         name="listingId",
     *         in="path",
     *         description="ID of the listing",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Minimum amount retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="minimum_amount", type="number", format="float", example=1501.00),
     *             @OA\Property(property="current_highest", type="number", format="float", example=1500.00, nullable=true),
     *             @OA\Property(property="listing_minimum_bid", type="number", format="float", example=1000.00, nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Submissions not allowed for this listing",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Submissions are not allowed for this listing.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Listing not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Listing not found.")
     *         )
     *     )
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
     * @OA\Patch(
     *     path="/api/submissions/{submissionId}/accept",
     *     summary="Accept a SOOM submission",
     *     description="Accept a specific SOOM submission and automatically reject all other pending SOOMs for the same listing",
     *     operationId="acceptSoom",
     *     tags={"SOOMs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="submissionId",
     *         in="path",
     *         description="ID of the submission to accept",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SOOM accepted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="SOOM accepted successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="listing_id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=2),
     *                 @OA\Property(property="amount", type="number", format="float", example=1500.00),
     *                 @OA\Property(property="status", type="string", example="accepted"),
     *                 @OA\Property(property="acceptance_date", type="string", format="datetime", example="2024-09-18T16:30:00.000000Z"),
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="first_name", type="string", example="John"),
     *                     @OA\Property(property="last_name", type="string", example="Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com")
     *                 )
     *             ),
     *             @OA\Property(property="validation_deadline", type="string", format="datetime", example="2024-09-23T16:30:00.000000Z"),
     *             @OA\Property(property="rejected_sooms_count", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized. User must be logged in.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Only seller can accept SOOMs",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Only the seller can accept SOOMs for this listing.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Submission not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Submission not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="SOOM already accepted",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="This SOOM has already been accepted.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to accept SOOM"),
     *             @OA\Property(property="details", type="string")
     *         )
     *     )
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

            $submission = Submission::with(['listing', 'user'])->find($submissionId);

            if (!$submission) {
                return response()->json([
                    'message' => 'Submission not found.',
                ], 404);
            }

            // Vérifications...
            if ($submission->listing->seller_id != $userId) {
                return response()->json([
                    'message' => 'Only the seller can accept SOOMs for this listing.',
                ], 403);
            }

            if ($submission->status === 'accepted') {
                return response()->json([
                    'message' => 'This SOOM has already been accepted.',
                ], 422);
            }

            // Accepter ce SOOM avec acceptance_date
            $acceptanceDate = now();
            $submission->update([
                'status' => 'accepted',
                'acceptance_date' => $acceptanceDate
            ]);

            // SUPPRIMÉ: Le rejet automatique des autres SOOMs
            // Les autres SOOMs restent en status "pending"
            // Le vendeur peut accepter plusieurs SOOMs et choisir lequel valider

            $seller = Auth::user();

            try {
                // Envoyer l'email aux DEUX parties
                $emails = [$submission->user->email, $seller->email];
                Mail::to($emails)->send(new SoomAcceptedMail($submission, $submission->listing, $seller));
                \Log::info('SOOM accepted email sent successfully to both parties: ' . implode(', ', $emails));
            } catch (\Exception $e) {
                \Log::error('Failed to send SOOM accepted email: ' . $e->getMessage());
            }

            DB::commit();

            $validationDeadline = Carbon::parse($acceptanceDate)->addDays(5);

            // Compter les SOOMs pending restants pour information
            $remainingPendingCount = Submission::where('listing_id', $submission->listing_id)
                ->where('id', '!=', $submissionId)
                ->where('status', 'pending')
                ->count();

            return response()->json([
                'message' => 'SOOM accepted successfully',
                'data' => $submission->load(['user:id,first_name,last_name,email', 'listing']),
                'validation_deadline' => $validationDeadline->toISOString(),
                'remaining_pending_sooms' => $remainingPendingCount
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('ACCEPT SOOM ERROR: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to accept SOOM',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Patch(
     *     path="/api/submissions/{submissionId}/reject",
     *     summary="Reject a SOOM submission",
     *     description="Reject a specific SOOM submission with optional rejection reason",
     *     operationId="rejectSoom",
     *     tags={"SOOMs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="submissionId",
     *         in="path",
     *         description="ID of the submission to reject",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Optional rejection reason",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="reason",
     *                 type="string",
     *                 description="Reason for rejection",
     *                 example="Price too low for current market conditions"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SOOM rejected successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="SOOM rejected successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="listing_id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=2),
     *                 @OA\Property(property="amount", type="number", format="float", example=1500.00),
     *                 @OA\Property(property="status", type="string", example="rejected"),
     *                 @OA\Property(property="rejection_reason", type="string", example="Price too low for current market conditions", nullable=true),
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="first_name", type="string", example="John"),
     *                     @OA\Property(property="last_name", type="string", example="Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized. User must be logged in.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied or cannot reject validated sale",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string",
     *                 example="Only the seller can reject SOOMs for this listing.",
     *                 enum={"Only the seller can reject SOOMs for this listing.", "Cannot reject a SOOM with validated sale."}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Submission not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Submission not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="SOOM already rejected",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="This SOOM has already been rejected.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to reject SOOM"),
     *             @OA\Property(property="details", type="string")
     *         )
     *     )
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

            $submission = Submission::with(['listing', 'user'])->find($submissionId);

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

            // Vérifier que le SOOM n'est pas déjà accepté ET validé
            if ($submission->status === 'accepted' && $submission->sale_validated) {
                return response()->json([
                    'message' => 'Cannot reject a SOOM with validated sale.',
                ], 403);
            }

            // Préparer les données de mise à jour
            $updateData = ['status' => 'rejected'];

            // Ajouter la raison de rejet si fournie
            if ($request->has('reason') && !empty($request->reason)) {
                $updateData['rejection_reason'] = $request->reason;
            }

            // Rejeter ce SOOM
            $submission->update($updateData);

            // Envoyer notification email à l'acheteur
            $seller = Auth::user();
            try {
                Mail::to($submission->user->email)->send(new SoomRejectedMail($submission, $submission->listing, $seller, $request->reason));
            } catch (\Exception $e) {
                \Log::error('Failed to send SOOM rejected email: ' . $e->getMessage());
            }

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
     * @OA\Post(
     *     path="/api/submissions/{submissionId}/validate-sale",
     *     summary="Validate a sale after SOOM acceptance",
     *     description="Validate a sale for an accepted SOOM within 5 days of acceptance. Only sellers can validate sales.",
     *     operationId="validateSale",
     *     tags={"SOOMs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="submissionId",
     *         in="path",
     *         description="ID of the accepted submission to validate",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sale validated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Sale validated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="listing_id", type="integer", example=1),
     *                 @OA\Property(property="seller_id", type="integer", example=3),
     *                 @OA\Property(property="buyer_id", type="integer", example=2),
     *                 @OA\Property(property="bid_amount", type="number", format="float", example=1500.00),
     *                 @OA\Property(property="bid_date", type="string", format="datetime", example="2024-09-18T16:30:00.000000Z"),
     *                 @OA\Property(property="validated", type="boolean", example=true),
     *                 @OA\Property(property="validated_at", type="string", format="datetime", example="2024-09-23T16:30:00.000000Z"),
     *                 @OA\Property(property="validator_id", type="integer", example=3)
     *             ),
     *             @OA\Property(
     *                 property="submission",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="listing_id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=2),
     *                 @OA\Property(property="amount", type="number", format="float", example=1500.00),
     *                 @OA\Property(property="status", type="string", example="accepted"),
     *                 @OA\Property(property="sale_validated", type="boolean", example=true),
     *                 @OA\Property(property="sale_validation_date", type="string", format="datetime", example="2024-09-23T16:30:00.000000Z"),
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="first_name", type="string", example="John"),
     *                     @OA\Property(property="last_name", type="string", example="Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="phone", type="string", example="+1234567890", nullable=true)
     *                 ),
     *                 @OA\Property(
     *                     property="listing",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Yamaha R1"),
     *                     @OA\Property(property="description", type="string", example="Great motorcycle")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - User must be logged in",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized. User must be logged in.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied or invalid conditions",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string",
     *                 example="Only the seller can validate sales for this listing.",
     *                 enum={
     *                     "Only the seller can validate sales for this listing.",
     *                     "Only accepted SOOMs can be validated."
     *                 }
     *             ),
     *             @OA\Property(property="current_status", type="string", example="pending", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Submission not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Submission not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation period expired or already validated",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="This sale has already been validated.")
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="Validation period has expired. You had 5 days to validate this sale."),
     *                     @OA\Property(property="acceptance_date", type="string", format="datetime", example="2024-09-18T16:30:00.000000Z"),
     *                     @OA\Property(property="validation_deadline", type="string", format="datetime", example="2024-09-23T16:30:00.000000Z")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to validate sale"),
     *             @OA\Property(property="details", type="string")
     *         )
     *     )
     * )
     */
    public function validateSale($submissionId)
    {
        DB::beginTransaction();

        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'message' => 'Unauthorized. User must be logged in.',
                ], 401);
            }

            $submission = Submission::with(['listing.seller', 'user'])->find($submissionId);

            if (!$submission) {
                return response()->json([
                    'message' => 'Submission not found.',
                ], 404);
            }

            // Vérifications...
            if ($submission->listing->seller_id != $userId) {
                return response()->json([
                    'message' => 'Only the seller can validate sales for this listing.',
                ], 403);
            }

            if ($submission->status !== 'accepted') {
                return response()->json([
                    'message' => 'Only accepted SOOMs can be validated.',
                    'current_status' => $submission->status
                ], 403);
            }

            if ($submission->sale_validated) {
                return response()->json([
                    'message' => 'This sale has already been validated.',
                ], 422);
            }

            // Vérifier la période de validation (5 jours)
            $validationDeadline = Carbon::parse($submission->acceptance_date)->addDays(5);
            if (now()->gt($validationDeadline)) {
                return response()->json([
                    'message' => 'Validation period has expired. You had 5 days to validate this sale.',
                    'acceptance_date' => $submission->acceptance_date,
                    'validation_deadline' => $validationDeadline->toISOString()
                ], 422);
            }

            // Marquer la soumission comme validée
            $submission->update([
                'sale_validated' => true,
                'sale_validation_date' => now()
            ]);

            // MAINTENANT rejeter tous les autres SOOMs pending pour ce listing
            Submission::where('listing_id', $submission->listing_id)
                ->where('id', '!=', $submissionId)
                ->where('status', 'pending')
                ->update(['status' => 'rejected']);

            // Créer l'enregistrement dans auction_histories
            $auctionHistory = AuctionHistory::create([
                'listing_id' => $submission->listing_id,
                'seller_id' => $submission->listing->seller_id,
                'buyer_id' => $submission->user_id,
                'bid_amount' => $submission->amount,
                'bid_date' => $submission->submission_date,
                'validated' => true,
                'validated_at' => now(),
                'validator_id' => $userId,
            ]);

            // CORRECTION: Simplifier l'envoi d'email et ajouter plus de logs
            $seller = $submission->listing->seller;
            $buyer = $submission->user;

            try {
                \Log::info('=== ATTEMPTING SALE VALIDATION EMAIL ===');
                \Log::info('Seller email: ' . $seller->email);
                \Log::info('Buyer email: ' . $buyer->email);

                // Créer l'instance de mail
                $mailInstance = new SaleValidatedMail($auctionHistory, $submission, $seller, $buyer);

                // Envoyer à chaque destinataire séparément pour plus de contrôle
                Mail::to($seller->email)->send($mailInstance);
                \Log::info('Email sent to seller: ' . $seller->email);

                Mail::to($buyer->email)->send($mailInstance);
                \Log::info('Email sent to buyer: ' . $buyer->email);

                \Log::info('=== SALE VALIDATION EMAILS SENT SUCCESSFULLY ===');
            } catch (\Exception $e) {
                \Log::error('=== SALE VALIDATION EMAIL ERROR ===');
                \Log::error('Error message: ' . $e->getMessage());
                \Log::error('Error file: ' . $e->getFile());
                \Log::error('Error line: ' . $e->getLine());
                \Log::error('Stack trace: ' . $e->getTraceAsString());

                // Ne pas faire échouer la transaction pour un problème d'email
                // L'important est que la validation soit enregistrée
            }

            DB::commit();

            return response()->json([
                'message' => 'Sale validated successfully',
                'data' => $auctionHistory,
                'submission' => $submission->load(['user:id,first_name,last_name,email,phone', 'listing'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('VALIDATE SALE ERROR: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to validate sale',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/validated-sales",
     *     summary="Get all validated sales for authenticated user",
     *     description="Retrieve all validated sales where the user is either seller or buyer",
     *     operationId="getValidatedSales",
     *     tags={"SOOMs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Validated sales retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validated sales retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="listing_id", type="integer", example=1),
     *                     @OA\Property(property="seller_id", type="integer", example=3),
     *                     @OA\Property(property="buyer_id", type="integer", example=2),
     *                     @OA\Property(property="bid_amount", type="number", format="float", example=1500.00),
     *                     @OA\Property(property="validated", type="boolean", example=true),
     *                     @OA\Property(property="validated_at", type="string", format="datetime"),
     *                     @OA\Property(
     *                         property="listing",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="title", type="string", example="Yamaha R1"),
     *                         @OA\Property(property="description", type="string", example="Great motorcycle")
     *                     ),
     *                     @OA\Property(
     *                         property="buyer",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="first_name", type="string", example="John"),
     *                         @OA\Property(property="last_name", type="string", example="Doe"),
     *                         @OA\Property(property="email", type="string", example="john@example.com")
     *                     ),
     *                     @OA\Property(
     *                         property="seller",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="first_name", type="string", example="Jane"),
     *                         @OA\Property(property="last_name", type="string", example="Smith"),
     *                         @OA\Property(property="email", type="string", example="jane@example.com")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="stats",
     *                 type="object",
     *                 @OA\Property(property="total_sales", type="integer", example=5),
     *                 @OA\Property(property="as_seller", type="integer", example=2),
     *                 @OA\Property(property="as_buyer", type="integer", example=3),
     *                 @OA\Property(property="total_amount", type="number", format="float", example=7500.00)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized. User must be logged in.")
     *         )
     *     )
     * )
     */
    public function getValidatedSales()
    {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'message' => 'Unauthorized. User must be logged in.',
            ], 401);
        }

        // Récupérer toutes les ventes validées où l'utilisateur est vendeur ou acheteur
        $sales = AuctionHistory::where(function ($query) use ($userId) {
            $query->where('seller_id', $userId)
                ->orWhere('buyer_id', $userId);
        })
            ->where('validated', true)
            ->with([
                'listing:id,title,description',
                'buyer:id,first_name,last_name,email',
                'seller:id,first_name,last_name,email'
            ])
            ->orderBy('validated_at', 'desc')
            ->get();

        // Statistiques
        $stats = [
            'total_sales' => $sales->count(),
            'as_seller' => $sales->where('seller_id', $userId)->count(),
            'as_buyer' => $sales->where('buyer_id', $userId)->count(),
            'total_amount' => $sales->sum('bid_amount')
        ];

        return response()->json([
            'message' => 'Validated sales retrieved successfully',
            'data' => $sales,
            'stats' => $stats
        ]);
    }
    /**
     * @OA\Get(
     *     path="/api/my-listings-sooms",
     *     summary="Get SOOMs received on user's listings",
     *     description="Retrieve all SOOMs submitted on listings owned by the authenticated user",
     *     operationId="getMyListingsSooms",
     *     tags={"SOOMs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="SOOMs retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="SOOMs retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="listing_id", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=2),
     *                     @OA\Property(property="amount", type="number", format="float", example=1500.00),
     *                     @OA\Property(property="status", type="string", example="pending"),
     *                     @OA\Property(property="submission_date", type="string", format="datetime"),
     *                     @OA\Property(property="sale_validated", type="boolean", example=false),
     *                     @OA\Property(
     *                         property="user",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="first_name", type="string", example="John"),
     *                         @OA\Property(property="last_name", type="string", example="Doe"),
     *                         @OA\Property(property="email", type="string", example="john@example.com")
     *                     ),
     *                     @OA\Property(
     *                         property="listing",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="title", type="string", example="Yamaha R1"),
     *                         @OA\Property(property="description", type="string", example="Great motorcycle"),
     *                         @OA\Property(property="seller_id", type="integer", example=3)
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="stats",
     *                 type="object",
     *                 @OA\Property(property="total_sooms", type="integer", example=10),
     *                 @OA\Property(property="pending_sooms", type="integer", example=5),
     *                 @OA\Property(property="accepted_sooms", type="integer", example=3),
     *                 @OA\Property(property="rejected_sooms", type="integer", example=2),
     *                 @OA\Property(property="pending_validation", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized. User must be logged in.")
     *         )
     *     )
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
            'pending_validation' => $sooms->where('status', 'accepted')->where('sale_validated', false)->count(),
        ];

        return response()->json([
            'message' => 'SOOMs retrieved successfully',
            'data' => $sooms,
            'stats' => $stats
        ]);
    }
    /**
     * @OA\Get(
     *     path="/api/my-sooms",
     *     summary="Get user's submitted SOOMs",
     *     description="Retrieve all SOOMs submitted by the authenticated user",
     *     operationId="getMySooms",
     *     tags={"SOOMs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="My SOOMs retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="My SOOMs retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="listing_id", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=2),
     *                     @OA\Property(property="amount", type="number", format="float", example=1500.00),
     *                     @OA\Property(property="status", type="string", example="pending"),
     *                     @OA\Property(property="submission_date", type="string", format="datetime"),
     *                     @OA\Property(
     *                         property="listing",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="title", type="string", example="Yamaha R1"),
     *                         @OA\Property(property="description", type="string", example="Great motorcycle"),
     *                         @OA\Property(property="seller_id", type="integer", example=3),
     *                         @OA\Property(property="country_id", type="integer", example=1),
     *                         @OA\Property(
     *                             property="seller",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=3),
     *                             @OA\Property(property="first_name", type="string", example="Jane"),
     *                             @OA\Property(property="last_name", type="string", example="Smith"),
     *                             @OA\Property(property="email", type="string", example="jane@example.com")
     *                         ),
     *                         @OA\Property(
     *                             property="country",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="US"),
     *                             @OA\Property(property="name", type="string", example="United States")
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="stats",
     *                 type="object",
     *                 @OA\Property(property="total_sooms", type="integer", example=8),
     *                 @OA\Property(property="pending_sooms", type="integer", example=3),
     *                 @OA\Property(property="accepted_sooms", type="integer", example=2),
     *                 @OA\Property(property="rejected_sooms", type="integer", example=3)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized. User must be logged in.")
     *         )
     *     )
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
                'listing.country:id,code,name',
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
     * @OA\Delete(
     *     path="/api/submissions/{submissionId}/cancel",
     *     summary="Cancel a pending SOOM",
     *     description="Cancel a SOOM submission that is still in pending status",
     *     operationId="cancelSoom",
     *     tags={"SOOMs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="submissionId",
     *         in="path",
     *         description="ID of the submission to cancel",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SOOM cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="SOOM cancelled successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="listing_id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=2),
     *                 @OA\Property(property="amount", type="number", format="float", example=1500.00),
     *                 @OA\Property(property="status", type="string", example="pending")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized. User must be logged in.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied or invalid status",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string",
     *                 example="You can only cancel your own SOOM submissions.",
     *                 enum={"You can only cancel your own SOOM submissions.", "You can only cancel pending SOOM submissions."}
     *             ),
     *             @OA\Property(property="current_status", type="string", example="accepted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Submission not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Submission not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to cancel SOOM"),
     *             @OA\Property(property="details", type="string")
     *         )
     *     )
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
     * @OA\Put(
     *     path="/api/submissions/{submissionId}/edit",
     *     summary="Edit a pending SOOM",
     *     description="Modify the amount of a SOOM submission that is still in pending status",
     *     operationId="editSoom",
     *     tags={"SOOMs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="submissionId",
     *         in="path",
     *         description="ID of the submission to edit",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Updated SOOM data",
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(
     *                 property="amount",
     *                 type="number",
     *                 format="float",
     *                 description="New amount for the SOOM",
     *                 example=1600.00,
     *                 minimum=0
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SOOM updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="SOOM updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="amount", type="number", format="float", example=1600.00),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="submission_date", type="string", format="datetime"),
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="first_name", type="string", example="John"),
     *                     @OA\Property(property="last_name", type="string", example="Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com")
     *                 )
     *             ),
     *             @OA\Property(property="previous_amount", type="number", format="float", example=1500.00),
     *             @OA\Property(property="current_highest", type="number", format="float", example=1600.00)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized. User must be logged in.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied or invalid conditions",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string",
     *                 example="You can only edit your own SOOM submissions.",
     *                 enum={
     *                     "You can only edit your own SOOM submissions.",
     *                     "You can only edit pending SOOM submissions.",
     *                     "Submissions are no longer allowed for this listing."
     *                 }
     *             ),
     *             @OA\Property(property="current_status", type="string", example="accepted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Submission not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Submission not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or amount too low",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="Validation failed"),
     *                     @OA\Property(
     *                         property="errors",
     *                         type="object",
     *                         @OA\Property(
     *                             property="amount",
     *                             type="array",
     *                             @OA\Items(type="string", example="The amount field is required.")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="The SOOM amount must be at least 1501."),
     *                     @OA\Property(property="minimum_required", type="number", format="float", example=1501.00),
     *                     @OA\Property(property="current_highest_other", type="number", format="float", example=1500.00, nullable=true),
     *                     @OA\Property(property="your_current_amount", type="number", format="float", example=1400.00)
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to update SOOM"),
     *             @OA\Property(property="details", type="string")
     *         )
     *     )
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
                'submission_date' => now(),
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
     * @OA\Get(
     *     path="/api/listings/{listingId}/last-soom",
     *     summary="Get the last SOOM for a listing",
     *     description="Get the most recent SOOM submission for a specific listing with user context information",
     *     operationId="getLastSoom",
     *     tags={"SOOMs"},
     *     @OA\Parameter(
     *         name="listingId",
     *         in="path",
     *         description="ID of the listing",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Last SOOM retrieved successfully or no SOOMs found",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     description="When SOOMs exist",
     *                     @OA\Property(property="message", type="string", example="Last SOOM retrieved successfully"),
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="amount", type="number", format="float", example=1500.00),
     *                         @OA\Property(property="status", type="string", example="pending"),
     *                         @OA\Property(property="submission_date", type="string", format="datetime")
     *                     ),
     *                     @OA\Property(property="has_sooms", type="boolean", example=true),
     *                     @OA\Property(property="total_sooms_count", type="integer", example=5),
     *                     @OA\Property(property="minimum_bid_required", type="number", format="float", example=1501.00)
     *                 ),
     *                 @OA\Schema(
     *                     description="When no SOOMs exist",
     *                     @OA\Property(property="message", type="string", example="No SOOMs found for this listing"),
     *                     @OA\Property(property="data", type="null"),
     *                     @OA\Property(property="has_sooms", type="boolean", example=false),
     *                     @OA\Property(property="total_sooms_count", type="integer", example=0),
     *                     @OA\Property(property="minimum_bid_required", type="number", format="float", example=1000.00)
     *                 )
     *             },
     *             @OA\Property(property="listing_minimum_bid", type="number", format="float", example=1000.00),
     *             @OA\Property(property="is_seller", type="boolean", example=false),
     *             @OA\Property(property="user_has_pending_soom", type="boolean", example=true),
     *             @OA\Property(
     *                 property="user_pending_soom",
     *                 type="object",
     *                 nullable=true,
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="amount", type="number", format="float", example=1400.00),
     *                 @OA\Property(property="status", type="string", example="pending")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Listing not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Listing not found.")
     *         )
     *     )
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

        // Vérifier si l'utilisateur connecté est le vendeur
        $userId = Auth::id();
        $isSeller = $userId && $listing->seller_id == $userId;

        // Vérifier si l'utilisateur connecté a déjà un SOOM pending sur ce listing
        $userPendingSoom = null;
        $userHasPendingSoom = false;

        if ($userId && !$isSeller) {
            $userPendingSoom = Submission::where('listing_id', $listingId)
                ->where('user_id', $userId)
                ->where('status', 'pending')
                ->with('user:id,first_name,last_name,email')
                ->first();

            $userHasPendingSoom = $userPendingSoom !== null;
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
                'listing_minimum_bid' => (float) ($listing->minimum_bid ?? 0),
                'is_seller' => $isSeller,
                'user_has_pending_soom' => $userHasPendingSoom,
                'user_pending_soom' => $userPendingSoom
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
            'minimum_bid_required' => (float) $minimumBidRequired,
            'is_seller' => $isSeller,
            'user_has_pending_soom' => $userHasPendingSoom,
            'user_pending_soom' => $userPendingSoom
        ], 200);
    }
    /**
     * @OA\Get(
     *     path="/api/pending-validations",
     *     summary="Get pending sale validations",
     *     description="Get all accepted SOOMs awaiting sale validation from the authenticated seller",
     *     operationId="getPendingValidations",
     *     tags={"SOOMs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Pending validations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Pending validations retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="amount", type="number", format="float", example=1500.00),
     *                     @OA\Property(property="status", type="string", example="accepted"),
     *                     @OA\Property(property="acceptance_date", type="string", format="datetime"),
     *                     @OA\Property(property="sale_validated", type="boolean", example=false),
     *                     @OA\Property(
     *                         property="user",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="first_name", type="string", example="John"),
     *                         @OA\Property(property="last_name", type="string", example="Doe"),
     *                         @OA\Property(property="email", type="string", example="john@example.com")
     *                     ),
     *                     @OA\Property(
     *                         property="listing",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="title", type="string", example="Yamaha R1"),
     *                         @OA\Property(property="description", type="string", example="Great motorcycle"),
     *                         @OA\Property(property="seller_id", type="integer", example=3)
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="total_pending", type="integer", example=3),
     *             @OA\Property(property="expired_count", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized. User must be logged in.")
     *         )
     *     )
     * )
     */

    public function getPendingValidations()
    {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'message' => 'Unauthorized. User must be logged in.',
            ], 401);
        }

        // Récupérer tous les SOOMs acceptés non validés sur mes listings
        $pendingValidations = Submission::whereHas('listing', function ($query) use ($userId) {
            $query->where('seller_id', $userId);
        })
            ->where('status', 'accepted')
            ->where('sale_validated', false)
            ->with([
                'user:id,first_name,last_name,email',
                'listing:id,title,description,seller_id'
            ])
            ->orderBy('acceptance_date', 'asc')
            ->get();

        // Calculer ceux qui expirent bientôt (dans les 24h)
        $expiringSoon = $pendingValidations->filter(function ($submission) {
            $deadline = Carbon::parse($submission->acceptance_date)->addDays(5);
            return $deadline->diffInHours(now()) <= 24 && $deadline->isFuture();
        });

        return response()->json([
            'message' => 'Pending validations retrieved successfully',
            'data' => $pendingValidations,
            'total_pending' => $pendingValidations->count(),
            'expired_count' => $expiringSoon->count()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/soom-stats",
     *     summary="Get SOOM statistics for authenticated user",
     *     description="Get comprehensive statistics for SOOMs as both seller and buyer",
     *     operationId="getSoomStats",
     *     tags={"SOOMs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Statistics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="as_seller",
     *                     type="object",
     *                     @OA\Property(property="total_received", type="integer", example=15),
     *                     @OA\Property(property="pending", type="integer", example=5),
     *                     @OA\Property(property="accepted", type="integer", example=4),
     *                     @OA\Property(property="rejected", type="integer", example=6),
     *                     @OA\Property(property="validated_sales", type="integer", example=3),
     *                     @OA\Property(property="pending_validation", type="integer", example=1)
     *                 ),
     *                 @OA\Property(
     *                     property="as_buyer",
     *                     type="object",
     *                     @OA\Property(property="total_sent", type="integer", example=8),
     *                     @OA\Property(property="pending", type="integer", example=3),
     *                     @OA\Property(property="accepted", type="integer", example=2),
     *                     @OA\Property(property="rejected", type="integer", example=3),
     *                     @OA\Property(property="validated_purchases", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized. User must be logged in.")
     *         )
     *     )
     * )
     */
    public function getSoomStats()
    {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'message' => 'Unauthorized. User must be logged in.',
            ], 401);
        }

        // Statistiques en tant que vendeur
        $sellerSooms = Submission::whereHas('listing', function ($query) use ($userId) {
            $query->where('seller_id', $userId);
        })->get();

        $sellerStats = [
            'total_received' => $sellerSooms->count(),
            'pending' => $sellerSooms->where('status', 'pending')->count(),
            'accepted' => $sellerSooms->where('status', 'accepted')->count(),
            'rejected' => $sellerSooms->where('status', 'rejected')->count(),
            'validated_sales' => $sellerSooms->where('sale_validated', true)->count(),
            'pending_validation' => $sellerSooms->where('status', 'accepted')->where('sale_validated', false)->count(),
        ];

        // Statistiques en tant qu'acheteur
        $buyerSooms = Submission::where('user_id', $userId)->get();

        $buyerStats = [
            'total_sent' => $buyerSooms->count(),
            'pending' => $buyerSooms->where('status', 'pending')->count(),
            'accepted' => $buyerSooms->where('status', 'accepted')->count(),
            'rejected' => $buyerSooms->where('status', 'rejected')->count(),
            'validated_purchases' => $buyerSooms->where('sale_validated', true)->count(),
        ];

        return response()->json([
            'message' => 'Statistics retrieved successfully',
            'data' => [
                'as_seller' => $sellerStats,
                'as_buyer' => $buyerStats
            ]
        ]);
    }

    public function testEmail()
    {
        try {
            \Log::info('Testing email functionality');

            // Test avec un email simple
            Mail::raw('Test email from SOOM system', function ($message) {
                $message->to('yucefr@gmail.com')
                    ->subject('SOOM Test Email');
            });

            \Log::info('Test email sent successfully');

            return response()->json([
                'message' => 'Test email sent successfully',
                'check_logs' => 'Check storage/logs/laravel.log for email content'
            ]);
        } catch (\Exception $e) {
            \Log::error('Test email failed: ' . $e->getMessage());

            return response()->json([
                'error' => 'Test email failed',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Patch(
     *     path="/api/listings/{listingId}/mark-as-sold",
     *     summary="Mark listing as sold",
     *     description="Mark a listing as sold when a transaction is completed. Only the seller can perform this action.",
     *     operationId="markListingAsSold",
     *     tags={"SOOMs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="listingId",
     *         in="path",
     *         description="ID of the listing to mark as sold",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Listing marked as sold successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Listing marked as sold successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Yamaha R1"),
     *                 @OA\Property(property="status", type="string", example="sold"),
     *                 @OA\Property(property="updated_at", type="string", format="datetime", example="2024-09-27T16:30:00.000000Z")
     *             ),
     *             @OA\Property(property="rejected_sooms_count", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized. User must be logged in.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Only the seller can mark this listing as sold.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Listing not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Listing not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid status",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="This listing is already sold or inactive."),
     *             @OA\Property(property="current_status", type="string", example="sold")
     *         )
     *     )
     * )
     */
    public function markListingAsSold(Request $request, $listingId)
    {
        DB::beginTransaction();

        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'message' => 'Unauthorized. User must be logged in.',
                ], 401);
            }

            $listing = Listing::find($listingId);

            if (!$listing) {
                return response()->json([
                    'message' => 'Listing not found.',
                ], 404);
            }

            // Vérifier que l'utilisateur est le propriétaire du listing
            if ($listing->seller_id != $userId) {
                return response()->json([
                    'message' => 'Only the seller can mark this listing as sold.',
                ], 403);
            }

            // Vérifier que le listing peut être marqué comme vendu
            if ($listing->status !== 'published') {
                return response()->json([
                    'message' => 'This listing is already sold or inactive.',
                    'current_status' => $listing->status
                ], 422);
            }

            // Mettre à jour le listing - utiliser seulement les colonnes existantes
            $listing->update([
                'status' => 'sold',
                'allow_submission' => false // Désactiver les nouvelles soumissions
            ]);

            // Rejeter automatiquement tous les SOOMs pending
            $rejectedSoomsCount = Submission::where('listing_id', $listingId)
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'rejection_reason' => 'Listing marked as sold by seller'
                ]);

            // Optionnel : Envoyer des notifications aux utilisateurs avec SOOMs pending
            $pendingSoomUsers = Submission::where('listing_id', $listingId)
                ->where('status', 'rejected')
                ->where('rejection_reason', 'Listing marked as sold by seller')
                ->with('user')
                ->get();

            foreach ($pendingSoomUsers as $submission) {
                try {
                    // Ici vous pouvez ajouter l'envoi d'email de notification
                    // Mail::to($submission->user->email)->send(new ListingSoldNotificationMail($listing, $submission));
                } catch (\Exception $e) {
                    \Log::error('Failed to send listing sold notification: ' . $e->getMessage());
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Listing marked as sold successfully',
                'data' => $listing->fresh(),
                'rejected_sooms_count' => $rejectedSoomsCount
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to mark listing as sold',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/listings/{listingId}/close",
     *     summary="Close/finalize listing",
     *     description="Close a listing when seller wants to end it without a sale (e.g., no agreement reached, decided not to sell, etc.). This action rejects all pending SOOMs.",
     *     operationId="closeListing",
     *     tags={"SOOMs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="listingId",
     *         in="path",
     *         description="ID of the listing to close",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Listing closed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Listing closed successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Yamaha R1"),
     *                 @OA\Property(property="status", type="string", example="inactive"),
     *                 @OA\Property(property="updated_at", type="string", format="datetime", example="2024-09-27T16:30:00.000000Z")
     *             ),
     *             @OA\Property(property="rejected_sooms_count", type="integer", example=5),
     *             @OA\Property(property="notified_users_count", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized. User must be logged in.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Only the seller can close this listing.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Listing not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Listing not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid status",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="This listing is already closed or sold."),
     *             @OA\Property(property="current_status", type="string", example="inactive")
     *         )
     *     )
     * )
     */
    public function closeListing(Request $request, $listingId)
    {
        DB::beginTransaction();

        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'message' => 'Unauthorized. User must be logged in.',
                ], 401);
            }

            $listing = Listing::find($listingId);

            if (!$listing) {
                return response()->json([
                    'message' => 'Listing not found.',
                ], 404);
            }

            // Vérifier que l'utilisateur est le propriétaire du listing
            if ($listing->seller_id != $userId) {
                return response()->json([
                    'message' => 'Only the seller can close this listing.',
                ], 403);
            }

            // Vérifier que le listing peut être fermé
            if ($listing->status !== 'published') {
                return response()->json([
                    'message' => 'This listing is already closed or sold.',
                    'current_status' => $listing->status
                ], 422);
            }

            // Mettre à jour le listing - utiliser le statut "inactive" qui existe déjà
            $listing->update([
                'status' => 'inactive',
                'allow_submission' => false // Désactiver les nouvelles soumissions
            ]);

            // Récupérer les SOOMs pending avant de les rejeter pour les notifications
            $pendingSooms = Submission::where('listing_id', $listingId)
                ->where('status', 'pending')
                ->with('user')
                ->get();

            // Rejeter automatiquement tous les SOOMs pending
            $rejectedSoomsCount = Submission::where('listing_id', $listingId)
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'rejection_reason' => 'Listing closed by seller'
                ]);

            // Envoyer des notifications aux utilisateurs avec SOOMs pending
            $notifiedUsersCount = 0;
            foreach ($pendingSooms as $submission) {
                try {
                    // Ici vous pouvez ajouter l'envoi d'email de notification
                    // Mail::to($submission->user->email)->send(new ListingClosedNotificationMail($listing, $submission));
                    $notifiedUsersCount++;
                } catch (\Exception $e) {
                    \Log::error('Failed to send listing closed notification: ' . $e->getMessage());
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Listing closed successfully',
                'data' => $listing->fresh(),
                'rejected_sooms_count' => $rejectedSoomsCount,
                'notified_users_count' => $notifiedUsersCount
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to close listing',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/listings/{listingId}/reopen",
     *     summary="Reopen a closed listing",
     *     description="Reopen a previously closed listing. Only the seller can perform this action.",
     *     operationId="reopenListing",
     *     tags={"SOOMs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="listingId",
     *         in="path",
     *         description="ID of the listing to reopen",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Optional reopening details",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="allow_submission",
     *                 type="boolean",
     *                 description="Whether to allow new SOOM submissions",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="reopening_notes",
     *                 type="string",
     *                 description="Notes about why the listing is being reopened",
     *                 example="Decided to continue selling after reconsideration",
     *                 nullable=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Listing reopened successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Listing reopened successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Yamaha R1"),
     *                 @OA\Property(property="status", type="string", example="published"),
     *                 @OA\Property(property="reopened_at", type="string", format="datetime", example="2024-09-27T16:30:00.000000Z"),
     *                 @OA\Property(property="allow_submission", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Cannot reopen listing",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Only closed listings can be reopened."),
     *             @OA\Property(property="current_status", type="string", example="published")
     *         )
     *     )
     * )
     */
    public function reopenListing(Request $request, $listingId)
    {
        DB::beginTransaction();

        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'message' => 'Unauthorized. User must be logged in.',
                ], 401);
            }

            $listing = Listing::find($listingId);

            if (!$listing) {
                return response()->json([
                    'message' => 'Listing not found.',
                ], 404);
            }

            if ($listing->seller_id != $userId) {
                return response()->json([
                    'message' => 'Only the seller can reopen this listing.',
                ], 403);
            }

            if ($listing->status !== 'closed') {
                return response()->json([
                    'message' => 'Only closed listings can be reopened.',
                    'current_status' => $listing->status
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'allow_submission' => 'boolean',
                'reopening_notes' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = [
                'status' => 'published',
                'reopened_at' => now(),
                'allow_submission' => $request->get('allow_submission', true),
                'closed_at' => null,
                'closing_reason' => null
            ];

            if ($request->has('reopening_notes')) {
                $updateData['reopening_notes'] = $request->reopening_notes;
            }

            $listing->update($updateData);

            DB::commit();

            return response()->json([
                'message' => 'Listing reopened successfully',
                'data' => $listing->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to reopen listing',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
