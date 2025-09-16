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
 *     @OA\Property(property="acceptance_date", type="string", format="date-time"),
 *     @OA\Property(property="sale_validated", type="boolean"),
 *     @OA\Property(property="sale_validation_date", type="string", format="date-time"),
 *     @OA\Property(property="rejection_reason", type="string"),
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

/**
 * @OA\Schema(
 *     schema="AuctionHistory",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="listing_id", type="integer"),
 *     @OA\Property(property="seller_id", type="integer"),
 *     @OA\Property(property="buyer_id", type="integer"),
 *     @OA\Property(property="bid_amount", type="number", format="float"),
 *     @OA\Property(property="bid_date", type="string", format="date-time"),
 *     @OA\Property(property="validated", type="boolean"),
 *     @OA\Property(property="validated_at", type="string", format="date-time"),
 *     @OA\Property(property="validator_id", type="integer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
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
     *             @OA\Property(property="data", type="object", ref="#/components/schemas/Submission"),
     *             @OA\Property(property="validation_deadline", type="string", format="date-time", description="Deadline for sale validation (5 days)")
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

            $seller = Auth::user();

            try {
                // MODIFICATION: Envoyer l'email aux DEUX parties
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
                'validation_deadline' => $validationDeadline->toISOString()
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
     *     @OA\Response(response=403, description="Forbidden - Only seller can reject or sale already validated"),
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
     * Valider la vente (pour le vendeur après acceptation du SOOM)
     * @OA\Post(
     *     path="/api/submissions/{submissionId}/validate-sale",
     *     summary="Validate sale after SOOM acceptance (seller only)",
     *     tags={"Soom"},
     *     @OA\Parameter(
     *         name="submissionId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID of the accepted submission to validate sale"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sale validated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Sale validated successfully"),
     *             @OA\Property(property="data", type="object", ref="#/components/schemas/AuctionHistory"),
     *             @OA\Property(property="submission", type="object", ref="#/components/schemas/Submission")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Only seller can validate or SOOM not accepted"),
     *     @OA\Response(response=404, description="Submission not found"),
     *     @OA\Response(response=422, description="Sale already validated or validation period expired")
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

            // DEBUG: Logs détaillés pour l'email
            \Log::info('=== SALE VALIDATION EMAIL DEBUG ===');
            \Log::info('Auction History ID: ' . $auctionHistory->id);
            \Log::info('Seller: ' . $submission->listing->seller->first_name . ' - ' . $submission->listing->seller->email);
            \Log::info('Buyer: ' . $submission->user->first_name . ' - ' . $submission->user->email);

            // Envoyer les emails avec les informations de contact
            $seller = $submission->listing->seller;
            $buyer = $submission->user;

            try {
                // Vérifier que la classe SaleValidatedMail existe
                if (!class_exists('App\Mail\SaleValidatedMail')) {
                    \Log::error('SaleValidatedMail class NOT found');
                    throw new \Exception('SaleValidatedMail class not found');
                }

                \Log::info('SaleValidatedMail class exists');

                // Créer l'instance
                $mailInstance = new SaleValidatedMail($auctionHistory, $submission, $seller, $buyer);
                \Log::info('Mail instance created successfully');

                $emails = [$seller->email, $buyer->email];
                \Log::info('Sending to: ' . implode(', ', $emails));

                // Envoyer l'email
                Mail::to($emails)->send($mailInstance);
                \Log::info('Sale validation email sent successfully');
            } catch (\Exception $e) {
                \Log::error('SALE VALIDATION EMAIL ERROR: ' . $e->getMessage());
                \Log::error('EMAIL TRACE: ' . $e->getTraceAsString());
            }

            \Log::info('=== END SALE VALIDATION EMAIL DEBUG ===');

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
     * Obtenir toutes les ventes validées
     * @OA\Get(
     *     path="/api/validated-sales",
     *     summary="Get all validated sales for the authenticated user",
     *     tags={"Soom"},
     *     @OA\Response(
     *         response=200,
     *         description="Validated sales retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validated sales retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AuctionHistory")),
     *             @OA\Property(property="stats", type="object",
     *                 @OA\Property(property="total_sales", type="integer"),
     *                 @OA\Property(property="as_seller", type="integer"),
     *                 @OA\Property(property="as_buyer", type="integer"),
     *                 @OA\Property(property="total_amount", type="number", format="float")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
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
     *                 @OA\Property(property="rejected_sooms", type="integer"),
     *                 @OA\Property(property="pending_validation", type="integer", description="Accepted SOOMs pending sale validation")
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
            'pending_validation' => $sooms->where('status', 'accepted')->where('sale_validated', false)->count(),
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

    /**
     * Obtenir les SOOMs en attente de validation (pour les vendeurs)
     * @OA\Get(
     *     path="/api/pending-validations",
     *     summary="Get SOOMs pending sale validation",
     *     tags={"Soom"},
     *     @OA\Response(
     *         response=200,
     *         description="Pending validations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Pending validations retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Submission")),
     *             @OA\Property(property="total_pending", type="integer"),
     *             @OA\Property(property="expired_count", type="integer", description="Number of validations that will expire soon")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
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
     * Obtenir les statistiques des SOOMs pour un utilisateur
     * @OA\Get(
     *     path="/api/soom-stats",
     *     summary="Get SOOM statistics for authenticated user",
     *     tags={"Soom"},
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Statistics retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="as_seller", type="object",
     *                     @OA\Property(property="total_received", type="integer"),
     *                     @OA\Property(property="pending", type="integer"),
     *                     @OA\Property(property="accepted", type="integer"),
     *                     @OA\Property(property="rejected", type="integer"),
     *                     @OA\Property(property="validated_sales", type="integer"),
     *                     @OA\Property(property="pending_validation", type="integer")
     *                 ),
     *                 @OA\Property(property="as_buyer", type="object",
     *                     @OA\Property(property="total_sent", type="integer"),
     *                     @OA\Property(property="pending", type="integer"),
     *                     @OA\Property(property="accepted", type="integer"),
     *                     @OA\Property(property="rejected", type="integer"),
     *                     @OA\Property(property="validated_purchases", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
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
}
