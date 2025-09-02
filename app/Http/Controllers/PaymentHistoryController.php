<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PaymentHistoryController extends Controller
{
    /**
     * Récupérer l'historique des paiements d'un utilisateur spécifique
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function historyPaymentByUser(Request $request): JsonResponse
    {
        try {
            // Récupérer l'utilisateur authentifié ou un ID utilisateur spécifique
            $userId = $request->input('user_id') ?? Auth::id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Paramètres de pagination
            $perPage = $request->input('per_page', 15);
            $page = $request->input('page', 1);

            // Filtres optionnels
            $status = $request->input('status'); // completed, pending, failed
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            $minAmount = $request->input('min_amount');
            $maxAmount = $request->input('max_amount');

            // Construction de la requête
            $query = Payment::with(['user', 'listing', 'paymentMethod', 'bankCard'])
                ->where('user_id', $userId);

            // Application des filtres
            if ($status) {
                $query->where('payment_status', $status);
            }

            if ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            if ($minAmount) {
                $query->where('amount', '>=', $minAmount);
            }

            if ($maxAmount) {
                $query->where('amount', '<=', $maxAmount);
            }

            // Tri par date décroissante
            $query->orderBy('created_at', 'desc');

            // Pagination
            $payments = $query->paginate($perPage, ['*'], 'page', $page);

            // Calcul des statistiques pour cet utilisateur
            $stats = [
                'total_payments' => Payment::where('user_id', $userId)->count(),
                'completed_payments' => Payment::where('user_id', $userId)->completed()->count(),
                'pending_payments' => Payment::where('user_id', $userId)->pending()->count(),
                'failed_payments' => Payment::where('user_id', $userId)->failed()->count(),
                'total_amount' => Payment::where('user_id', $userId)->completed()->sum('amount'),
                'average_amount' => Payment::where('user_id', $userId)->completed()->avg('amount')
            ];

            return response()->json([
                'success' => true,
                'message' => 'Historique des paiements récupéré avec succès',
                'data' => [
                    'payments' => $payments->items(),
                    'pagination' => [
                        'current_page' => $payments->currentPage(),
                        'last_page' => $payments->lastPage(),
                        'per_page' => $payments->perPage(),
                        'total' => $payments->total(),
                        'from' => $payments->firstItem(),
                        'to' => $payments->lastItem()
                    ],
                    'statistics' => $stats
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'historique des paiements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer l'historique global de tous les paiements (Admin)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function historyPaymentGlobal(Request $request): JsonResponse
    {
        try {
            // Vérification des permissions admin (à adapter selon votre système)
            // if (!Auth::user()->isAdmin()) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Accès non autorisé'
            //     ], 403);
            // }

            // Paramètres de pagination
            $perPage = $request->input('per_page', 20);
            $page = $request->input('page', 1);

            // Filtres optionnels
            $status = $request->input('status');
            $userId = $request->input('user_id');
            $listingId = $request->input('listing_id');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            $minAmount = $request->input('min_amount');
            $maxAmount = $request->input('max_amount');
            $paymentMethod = $request->input('payment_method');
            $search = $request->input('search'); // Recherche par tran_ref ou user email

            // Construction de la requête
            $query = Payment::with(['user', 'listing', 'paymentMethod', 'bankCard']);

            // Application des filtres
            if ($status) {
                $query->where('payment_status', $status);
            }

            if ($userId) {
                $query->where('user_id', $userId);
            }

            if ($listingId) {
                $query->where('listing_id', $listingId);
            }

            if ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            if ($minAmount) {
                $query->where('amount', '>=', $minAmount);
            }

            if ($maxAmount) {
                $query->where('amount', '<=', $maxAmount);
            }

            if ($paymentMethod) {
                $query->where('payment_method_id', $paymentMethod);
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('tran_ref', 'like', "%{$search}%")
                      ->orWhereHas('user', function($userQuery) use ($search) {
                          $userQuery->where('email', 'like', "%{$search}%")
                                   ->orWhere('name', 'like', "%{$search}%");
                      });
                });
            }

            // Tri par date décroissante
            $query->orderBy('created_at', 'desc');

            // Pagination
            $payments = $query->paginate($perPage, ['*'], 'page', $page);

            // Calcul des statistiques globales
            $stats = [
                'total_payments' => Payment::count(),
                'completed_payments' => Payment::completed()->count(),
                'pending_payments' => Payment::pending()->count(),
                'failed_payments' => Payment::failed()->count(),
                'total_amount' => Payment::completed()->sum('amount'),
                'average_amount' => Payment::completed()->avg('amount'),
                'total_users' => Payment::distinct('user_id')->count(),
                'payments_today' => Payment::whereDate('created_at', today())->count(),
                'revenue_today' => Payment::whereDate('created_at', today())->completed()->sum('amount'),
                'payments_this_month' => Payment::whereMonth('created_at', now()->month)->count(),
                'revenue_this_month' => Payment::whereMonth('created_at', now()->month)->completed()->sum('amount')
            ];

            // Statistiques par statut
            $statusStats = Payment::selectRaw('payment_status, COUNT(*) as count, SUM(amount) as total_amount')
                ->groupBy('payment_status')
                ->get()
                ->keyBy('payment_status');

            // Statistiques par méthode de paiement
            $methodStats = Payment::with('paymentMethod')
                ->selectRaw('payment_method_id, COUNT(*) as count, SUM(amount) as total_amount')
                ->whereNotNull('payment_method_id')
                ->groupBy('payment_method_id')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Historique global des paiements récupéré avec succès',
                'data' => [
                    'payments' => $payments->items(),
                    'pagination' => [
                        'current_page' => $payments->currentPage(),
                        'last_page' => $payments->lastPage(),
                        'per_page' => $payments->perPage(),
                        'total' => $payments->total(),
                        'from' => $payments->firstItem(),
                        'to' => $payments->lastItem()
                    ],
                    'statistics' => [
                        'general' => $stats,
                        'by_status' => $statusStats,
                        'by_method' => $methodStats
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'historique global des paiements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les détails d'un paiement spécifique
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $payment = Payment::with(['user', 'listing', 'paymentMethod', 'bankCard'])
                ->findOrFail($id);

            // Vérification des permissions (l'utilisateur ne peut voir que ses propres paiements sauf admin)
            if (Auth::id() !== $payment->user_id && !Auth::user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé à ce paiement'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Détails du paiement récupérés avec succès',
                'data' => $payment
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Paiement non trouvé',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Obtenir les statistiques de paiement d'un utilisateur
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function userStats(Request $request): JsonResponse
    {
        try {
            $userId = $request->input('user_id') ?? Auth::id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $stats = [
                'total_payments' => Payment::where('user_id', $userId)->count(),
                'completed_payments' => Payment::where('user_id', $userId)->completed()->count(),
                'pending_payments' => Payment::where('user_id', $userId)->pending()->count(),
                'failed_payments' => Payment::where('user_id', $userId)->failed()->count(),
                'total_amount_paid' => Payment::where('user_id', $userId)->completed()->sum('amount'),
                'average_payment' => Payment::where('user_id', $userId)->completed()->avg('amount'),
                'last_payment' => Payment::where('user_id', $userId)->latest()->first(),
                'payments_this_month' => Payment::where('user_id', $userId)
                    ->whereMonth('created_at', now()->month)
                    ->count(),
                'amount_this_month' => Payment::where('user_id', $userId)
                    ->whereMonth('created_at', now()->month)
                    ->completed()
                    ->sum('amount')
            ];

            return response()->json([
                'success' => true,
                'message' => 'Statistiques utilisateur récupérées avec succès',
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
