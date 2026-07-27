<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuthLog;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Admin Auth Logs",
 *     description="Read-only audit trail of authentication events (register, login, logout, OTP delivery)"
 * )
 */
class AuthLogController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/auth-logs",
     *     summary="List authentication logs",
     *     tags={"Admin Auth Logs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="event", in="query", @OA\Schema(type="string", enum={"register","login","logout","otp_whatsapp","otp_email"})),
     *     @OA\Parameter(name="success", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", description="Matches identifier (email/phone) or user name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=25)),
     *     @OA\Response(response=200, description="Logs retrieved")
     * )
     */
    public function index(Request $request)
    {
        $query = AuthLog::with('user:id,first_name,last_name,email');

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('success')) {
            $query->where('success', $request->boolean('success'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('identifier', 'like', "%{$term}%")
                    ->orWhereHas('user', function ($uq) use ($term) {
                        $uq->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->latest()->paginate($request->get('per_page', 25));

        // User::$appends (is_dealer, points_of_interest, ...) survive column selection on
        // with() — hide them so listing logs doesn't drag in each user's full POI records.
        $logs->getCollection()->each(function ($log) {
            $log->user?->makeHidden(['is_dealer', 'dealer_title', 'dealer_address', 'dealer_phone', 'points_of_interest']);
        });

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/auth-logs/summary",
     *     summary="Auth log counts per event type (last 30 days)",
     *     tags={"Admin Auth Logs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Summary retrieved")
     * )
     */
    public function summary()
    {
        $since = now()->subDays(30);

        $counts = AuthLog::where('created_at', '>=', $since)
            ->selectRaw('event, success, count(*) as total')
            ->groupBy('event', 'success')
            ->get();

        $summary = [];
        foreach (['register', 'login', 'logout', 'otp_whatsapp', 'otp_email'] as $event) {
            $summary[$event] = [
                'success' => (int) $counts->where('event', $event)->where('success', true)->sum('total'),
                'failed' => (int) $counts->where('event', $event)->where('success', false)->sum('total'),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $summary,
            'since' => $since->toDateString(),
        ]);
    }
}
