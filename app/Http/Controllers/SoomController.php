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
     *     summary="Create a new SOOM",
     *     description="Create a new SOOM (Submission of Offer on Market) for a specific listing",
     *     operationId="createSoom",
     *     tags={"sooms"},
     *     @OA\Parameter(
     *         name="listingId",
     *         in="path",
     *         description="ID of the listing",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="amount", type="number", format="float", description="SOOM amount", example=1000.50)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="SOOM created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="SOOM created successfully"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="previous_highest", type="number", format="float", example=950.00)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthorized. User must be logged in."))
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Submissions are not allowed for this listing."))
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Listing not found",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Listing not found."))
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="The SOOM amount must be at least 1000."))
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(@OA\Property(property="error", type="string", example="Failed to create SOOM"))
     *     ),
     *     security={{"bearerAuth":{}}}
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
     *     summary="Get all SOOMs for a listing",
     *     description="Retrieve all SOOMs for a specific listing",
     *     operationId="getListingSooms",
     *     tags={"sooms"},
     *     @OA\Parameter(
     *         name="listingId",
     *         in="path",
     *         description="ID of the listing",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Submissions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Submissions retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="total_submissions", type="integer", example=5),
     *             @OA\Property(property="highest_amount", type="number", format="float", example=1500.00)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Listing not found",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Listing not found."))
     *     ),
     *     security={{"bearerAuth":{}}}
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
     *     path="/api/listings/{listingId}/soom/minimum",
     *     summary="Get minimum SOOM amount",
     *     description="Get the minimum amount required for a new SOOM submission",
     *     operationId="getMinimumSoomAmount",
     *     tags={"sooms"},
     *     @OA\Parameter(
     *         name="listingId",
     *         in="path",
     *         description="ID of the listing",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Minimum amount retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="minimum_amount", type="number", format="float", example=1001.00),
     *             @OA\Property(property="current_highest", type="number", format="float", example=1000.00),
     *             @OA\Property(property="listing_minimum_bid", type="number", format="float", example=500.00)
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Submissions not allowed",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Submissions are not allowed for this listing."))
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Listing not found",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Listing not found."))
     *     ),
     *     security={{"bearerAuth":{}}}
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
     * @OA\Post(
     *     path="/api/sooms/{submissionId}/accept",
     *     summary="Accept a SOOM",
     *     description="Accept a SOOM submission (only sellers can accept)",
     *     operationId="acceptSoom",
     *     tags={"sooms"},
     *     @OA\Parameter(
     *         name="submissionId",
     *         in="path",
     *         description="ID of the submission",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SOOM accepted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="SOOM accepted successfully"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="validation_deadline", type="string", format="date-time"),
     *             @OA\Property(property="rejected_sooms_count", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthorized. User must be logged in."))
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Only the seller can accept SOOMs for this listing."))
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Submission not found",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Submission not found."))
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Already accepted",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="This SOOM has already been accepted."))
     *     ),
     *     security={{"bearerAuth":{}}}
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

            // Rejeter automatiquement tous les autres SOOMs pending pour ce listing
            $rejectedCount = Submission::where('listing_id', $submission->listing_id)
                ->where('id', '!=', $submissionId)
                ->where('status', 'pending')
                ->update(['status' => 'rejected']);

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

            return response()->json([
                'message' => 'SOOM accepted successfully',
                'data' => $submission->load(['user:id,first_name,last_name,email', 'listing']),
                'validation_deadline' => $validationDeadline->toISOString(),
                'rejected_sooms_count' => $rejectedCount
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
     * @OA\Post(
     *     path="/api/sooms/{submissionId}/reject",
     *     summary="Reject a SOOM",
     *     description="Reject a SOOM submission with optional reason (only sellers can reject)",
     *     operationId="rejectSoom",
     *     tags={"sooms"},
     *     @OA\Parameter(
     *         name="submissionId",
     *         in="path",
     *         description="ID of the submission",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", description="Reason for rejection", example="Amount too low")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SOOM rejected successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="SOOM rejected successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthorized. User must be logged in."))
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Only the seller can reject SOOMs for this listing."))
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Submission not found",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Submission not found."))
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Already rejected",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="This SOOM has already been rejected."))
     *     ),
     *     security={{"bearerAuth":{}}}
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
     *     path="/api/sooms/{submissionId}/validate",
     *     summary="Validate a sale",
     *     description="Validate a sale for an accepted SOOM (only sellers can validate within 5 days)",
     *     operationId="validateSale",
     *     tags={"sooms"},
     *     @OA\Parameter(
     *         name="submissionId",
     *         in="path",
     *         description="ID of the submission",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sale validated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Sale validated successfully"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="submission", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthorized. User must be logged in."))
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Only accepted SOOMs can be validated."))
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Submission not found",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Submission not found."))
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Validation period has expired."))
     *     ),
     *     security={{"bearerAuth":{}}}
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

            // Rejeter tous les autres SOOMs pending pour ce listing
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
     *     summary="Get validated sales",
     *     description="Get all validated sales for the authenticated user (as seller or buyer)",
     *     operationId="getValidatedSales",
     *     tags={"sooms"},
     *     @OA\Response(
     *         response=200,
     *         description="Validated sales retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validated sales retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="stats", type="object",
     *                 @OA\Property(property="total_sales", type="integer", example=10),
     *                 @OA\Property(property="as_seller", type="integer", example=6),
     *                 @OA\Property(property="as_buyer", type="integer", example=4),
     *                 @OA\Property(property="total_amount", type="number", format="float", example=15000.50)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthorized. User must be logged in."))
     *     ),
     *     security={{"bearerAuth":{}}}
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
     *     path="/api/my-listings/sooms",
     *     summary="Get SOOMs for my listings",
     *     description="Get all SOOMs received on my listings",
     *     operationId="getMyListingsSooms",
     *     tags={"sooms"},
     *     @OA\Response(
     *         response=200,
     *         description="SOOMs retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="SOOMs retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="stats", type="object",
     *                 @OA\Property(property="total_sooms", type="integer", example=25),
     *                 @OA\Property(property="pending_sooms", type="integer", example=10),
     *                 @OA\Property(property="accepted_sooms", type="integer", example=8),
     *                 @OA\Property(property="rejected_sooms", type="integer", example=7),
     *                 @OA\Property(property="pending_validation", type="integer", example=2)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthorized. User must be logged in."))
     *     ),
     *     security={{"bearerAuth":{}}}
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
     *     summary="Get my SOOMs",
     *     description="Get all SOOMs I have submitted",
     *     operationId="getMySooms",
     *     tags={"sooms"},
     *     @OA\Response(
     *         response=200,
     *         description="My SOOMs retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="My SOOMs retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="stats", type="object",
     *                 @OA\Property(property="total_sooms", type="integer", example=15),
     *                 @OA\Property(property="pending_sooms", type="integer", example=5),
     *                 @OA\Property(property="accepted_sooms", type="integer", example=6),
     *                 @OA\Property(property="rejected_sooms", type="integer", example=4)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthorized. User must be logged in."))
     *     ),
     *     security={{"bearerAuth":{}}}
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
     *     path="/api/sooms/{submissionId}/cancel",
     *     summary="Cancel a SOOM",
     *     description="Cancel a pending SOOM submission (only the submitter can cancel)",
     *     operationId="cancelSoom",
     *     tags={"sooms"},
     *     @OA\Parameter(
     *         name="submissionId",
     *         in="path",
     *         description="ID of the submission",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SOOM cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="SOOM cancelled successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthorized. User must be logged in."))
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="You can only cancel your own SOOM submissions."))
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Submission not found",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Submission not found."))
     *     ),
     *     security={{"bearerAuth":{}}}
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
     *     path="/api/sooms/{submissionId}/edit",
     *     summary="Edit a SOOM",
     *     description="Edit a pending SOOM submission amount (only the submitter can edit)",
     *     operationId="editSoom",
     *     tags={"sooms"},
     *     @OA\Parameter(
     *         name="submissionId",
     *         in="path",
     *         description="ID of the submission",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="amount", type="number", format="float", description="New SOOM amount", example=1200.50)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SOOM updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="SOOM updated successfully"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="previous_amount", type="number", format="float", example=1000.00),
     *             @OA\Property(property="current_highest", type="number", format="float", example=1200.50)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthorized. User must be logged in."))
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="You can only edit your own SOOM submissions."))
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Submission not found",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Submission not found."))
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="The SOOM amount must be at least 1001."))
     *     ),
     *     security={{"bearerAuth":{}}}
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
     *     summary="Get last SOOM for a listing",
     *     description="Get the most recent SOOM for a specific listing with additional information",
     *     operationId="getLastSoom",
     *     tags={"sooms"},
     *     @OA\Parameter(
     *         name="listingId",
     *         in="path",
     *         description="ID of the listing",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Last SOOM retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Last SOOM retrieved successfully"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="has_sooms", type="boolean", example=true),
     *             @OA\Property(property="total_sooms_count", type="integer", example=5),
     *             @OA\Property(property="minimum_bid_required", type="number", format="float", example=1001.00),
     *             @OA\Property(property="is_seller", type="boolean", example=false),
     *             @OA\Property(property="user_has_pending_soom", type="boolean", example=true),
     *             @OA\Property(property="user_pending_soom", type="object", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Listing not found",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Listing not found."))
     *     ),
     *     security={{"bearerAuth":{}}}
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
     *     summary="Get pending validations",
     *     description="Get all accepted SOOMs awaiting validation for my listings",
     *     operationId="getPendingValidations",
     *     tags={"sooms"},
     *     @OA\Response(
     *         response=200,
     *         description="Pending validations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Pending validations retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="total_pending", type="integer", example=3),
     *             @OA\Property(property="expired_count", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthorized. User must be logged in."))
     *     ),
     *     security={{"bearerAuth":{}}}
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
     *     summary="Get SOOM statistics",
     *     description="Get statistics about SOOMs as seller and buyer for the authenticated user",
     *     operationId="getSoomStats",
     *     tags={"sooms"},
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Statistics retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="as_seller", type="object",
     *                     @OA\Property(property="total_received", type="integer", example=25),
     *                     @OA\Property(property="pending", type="integer", example=10),
     *                     @OA\Property(property="accepted", type="integer", example=8),
     *                     @OA\Property(property="rejected", type="integer", example=7),
     *                     @OA\Property(property="validated_sales", type="integer", example=5),
     *                     @OA\Property(property="pending_validation", type="integer", example=3)
     *                 ),
     *                 @OA\Property(property="as_buyer", type="object",
     *                     @OA\Property(property="total_sent", type="integer", example=15),
     *                     @OA\Property(property="pending", type="integer", example=5),
     *                     @OA\Property(property="accepted", type="integer", example=6),
     *                     @OA\Property(property="rejected", type="integer", example=4),
     *                     @OA\Property(property="validated_purchases", type="integer", example=3)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthorized. User must be logged in."))
     *     ),
     *     security={{"bearerAuth":{}}}
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

    /**
     * @OA\Post(
     *     path="/api/test-email",
     *     summary="Test email functionality",
     *     description="Send a test email to verify email configuration",
     *     operationId="testEmail",
     *     tags={"sooms"},
     *     @OA\Response(
     *         response=200,
     *         description="Test email sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Test email sent successfully"),
     *             @OA\Property(property="check_logs", type="string", example="Check storage/logs/laravel.log for email content")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Test email failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Test email failed"),
     *             @OA\Property(property="details", type="string")
     *         )
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
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
}

/**
 * @OA\Info(
 *     title="SOOM API Documentation",
 *     version="1.0.0",
 *     description="API for managing SOOM (Submission of Offer on Market) operations",
 *     @OA\Contact(
 *         email="admin@example.com",
 *         name="API Support"
 *     )
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local server"
 * )
 *
 * @OA\Server(
 *     url="https://api.example.com",
 *     description="Production server"
 * )
 *
 * @OA\Server(
 *     url="https://staging.api.example.com",
 *     description="Staging server"
 * )
 *
 * @OA\Server(
 *     url="https://dev.api.example.com",
 *     description="Development server"
 * )
 *
 * @OA\Tag(
 *     name="sooms",
 *     description="Operations related to SOOMs (Submission of Offer on Market)"
 * )
