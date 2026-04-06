<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportingController extends Controller
{
    // ─────────────────────────────────────────────
    // USERS REPORT
    // ─────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/admin/reporting/users",
     *     summary="Users report with export (PDF / Excel / CSV)",
     *     description="Returns a paginated JSON list of users (default) or triggers a file download when format=pdf|excel|csv. Supports filtering by status, role, date range, and search.",
     *     tags={"Admin - Reporting"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Response format",
     *         required=false,
     *         @OA\Schema(type="string", enum={"json","pdf","excel","csv"}, default="json", example="excel")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by active status (1 = active, 0 = inactive)",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0,1}, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="role_id",
     *         in="query",
     *         description="Filter by role ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by first name, last name, email, or phone",
     *         required=false,
     *         @OA\Schema(type="string", example="john")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter registrations from this date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter registrations up to this date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page (JSON format only)",
     *         required=false,
     *         @OA\Schema(type="integer", default=50, example=25)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Paginated JSON list (format=json) or file download (format=pdf|excel|csv)",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="success", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="data",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="total", type="integer", example=120),
     *                     @OA\Property(property="per_page", type="integer", example=50),
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="first_name", type="string", example="John"),
     *                             @OA\Property(property="last_name", type="string", example="Doe"),
     *                             @OA\Property(property="email", type="string", example="john@example.com"),
     *                             @OA\Property(property="phone", type="string", example="+212600000000"),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="gender", type="string", example="male"),
     *                             @OA\Property(property="created_at", type="string", example="2025-03-01 10:00")
     *                         )
     *                     )
     *                 )
     *             )
     *         ),
     *         @OA\MediaType(mediaType="application/pdf", @OA\Schema(type="string", format="binary")),
     *         @OA\MediaType(mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", @OA\Schema(type="string", format="binary")),
     *         @OA\MediaType(mediaType="text/csv", @OA\Schema(type="string", format="binary"))
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Unsupported format",
     *         @OA\JsonContent(@OA\Property(property="error", type="string", example="Unsupported format. Use: json, pdf, excel, csv"))
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthenticated"))),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthorized")))
     * )
     */
    public function users(Request $request)
    {
        $query = User::with(['role'])
            ->when($request->filled('status'), fn($q) => $q->where('is_active', $request->status))
            ->when($request->filled('role_id'), fn($q) => $q->where('role_id', $request->role_id))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(fn($qq) => $qq->where('first_name', 'like', "%$s%")
                    ->orWhere('last_name', 'like', "%$s%")
                    ->orWhere('email', 'like', "%$s%")
                    ->orWhere('phone', 'like', "%$s%"));
            });

        $format = strtolower($request->get('format', 'json'));

        if ($format === 'json') {
            $users = $query->paginate($request->get('per_page', 50));
            return response()->json(['success' => true, 'data' => $users]);
        }

        $users = $query->get();

        $columns = [
            'ID', 'First Name', 'Last Name', 'Email', 'Phone',
            'Role', 'Status', 'Gender', 'City', 'Country', 'Registered At',
        ];

        $rows = $users->map(fn($u) => [
            $u->id,
            $u->first_name,
            $u->last_name,
            $u->email,
            $u->phone,
            $u->role?->name ?? '—',
            $u->is_active ? 'Active' : 'Inactive',
            $u->gender ?? '—',
            $u->city ?? '—',
            $u->country ?? '—',
            $u->created_at?->format('Y-m-d H:i'),
        ])->toArray();

        $title = 'Users Report';
        $stats = [
            'Total'    => $users->count(),
            'Active'   => $users->where('is_active', true)->count(),
            'Inactive' => $users->where('is_active', false)->count(),
        ];

        return $this->export($format, 'users_report', $title, $columns, $rows, $stats);
    }

    // ─────────────────────────────────────────────
    // LISTINGS REPORT
    // ─────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/admin/reporting/listings",
     *     summary="Listings report with export (PDF / Excel / CSV)",
     *     description="Returns a paginated JSON list of listings (default) or triggers a file download when format=pdf|excel|csv. Supports filtering by status, category, seller, date range, and search.",
     *     tags={"Admin - Reporting"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Response format",
     *         required=false,
     *         @OA\Schema(type="string", enum={"json","pdf","excel","csv"}, default="json", example="pdf")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by listing status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"published","pending","rejected","draft"}, example="published")
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by category ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\Parameter(
     *         name="seller_id",
     *         in="query",
     *         description="Filter by seller (user) ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by listing title or ID",
     *         required=false,
     *         @OA\Schema(type="string", example="Honda")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter listings created from this date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter listings created up to this date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page (JSON format only)",
     *         required=false,
     *         @OA\Schema(type="integer", default=50, example=25)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Paginated JSON list (format=json) or file download (format=pdf|excel|csv)",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="success", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="data",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="total", type="integer", example=340),
     *                     @OA\Property(property="per_page", type="integer", example=50),
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="title", type="string", example="Honda CB500 2022"),
     *                             @OA\Property(property="price", type="number", example=45000),
     *                             @OA\Property(property="status", type="string", example="published"),
     *                             @OA\Property(property="views_count", type="integer", example=230),
     *                             @OA\Property(property="created_at", type="string", example="2025-04-01 09:00")
     *                         )
     *                     )
     *                 )
     *             )
     *         ),
     *         @OA\MediaType(mediaType="application/pdf", @OA\Schema(type="string", format="binary")),
     *         @OA\MediaType(mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", @OA\Schema(type="string", format="binary")),
     *         @OA\MediaType(mediaType="text/csv", @OA\Schema(type="string", format="binary"))
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Unsupported format",
     *         @OA\JsonContent(@OA\Property(property="error", type="string", example="Unsupported format. Use: json, pdf, excel, csv"))
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthenticated"))),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthorized")))
     * )
     */
    public function listings(Request $request)
    {
        $query = Listing::with(['category', 'seller', 'city', 'country', 'motorcycle.brand', 'motorcycle.model'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('category_id'), fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->filled('seller_id'), fn($q) => $q->where('seller_id', $request->seller_id))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(fn($qq) => $qq->where('title', 'like', "%$s%")->orWhere('id', $s));
            });

        $format = strtolower($request->get('format', 'json'));

        if ($format === 'json') {
            $listings = $query->paginate($request->get('per_page', 50));
            return response()->json(['success' => true, 'data' => $listings]);
        }

        $listings = $query->get();

        $columns = [
            'ID', 'Title', 'Category', 'Price', 'Status', 'Seller',
            'City', 'Country', 'Brand', 'Model', 'Views', 'Created At',
        ];

        $rows = $listings->map(fn($l) => [
            $l->id,
            $l->title,
            $l->category?->name ?? '—',
            $l->price,
            $l->status,
            $l->seller?->first_name . ' ' . $l->seller?->last_name,
            $l->city?->name ?? '—',
            $l->country?->name ?? '—',
            $l->motorcycle?->brand?->name ?? '—',
            $l->motorcycle?->model?->name ?? '—',
            $l->views_count ?? 0,
            $l->created_at?->format('Y-m-d H:i'),
        ])->toArray();

        $title = 'Listings Report';
        $stats = [
            'Total'     => $listings->count(),
            'Published' => $listings->where('status', 'published')->count(),
            'Pending'   => $listings->where('status', 'pending')->count(),
            'Rejected'  => $listings->where('status', 'rejected')->count(),
            'Draft'     => $listings->where('status', 'draft')->count(),
        ];

        return $this->export($format, 'listings_report', $title, $columns, $rows, $stats);
    }

    // ─────────────────────────────────────────────
    // PAYMENTS REPORT
    // ─────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/admin/reporting/payments",
     *     summary="Payments report with export (PDF / Excel / CSV)",
     *     description="Returns a paginated JSON list of payments (default) or triggers a file download when format=pdf|excel|csv. Supports filtering by status, user, listing, date range, and search by transaction reference.",
     *     tags={"Admin - Reporting"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Response format",
     *         required=false,
     *         @OA\Schema(type="string", enum={"json","pdf","excel","csv"}, default="json", example="csv")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by payment status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"completed","pending","failed"}, example="completed")
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="Filter by user ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\Parameter(
     *         name="listing_id",
     *         in="query",
     *         description="Filter by listing ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by transaction reference or cart ID",
     *         required=false,
     *         @OA\Schema(type="string", example="TST20250101")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter payments from this date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter payments up to this date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page (JSON format only)",
     *         required=false,
     *         @OA\Schema(type="integer", default=50, example=25)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Paginated JSON list (format=json) or file download (format=pdf|excel|csv)",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="success", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="data",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="total", type="integer", example=85),
     *                     @OA\Property(property="per_page", type="integer", example=50),
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="amount", type="number", example=150.00),
     *                             @OA\Property(property="total_amount", type="number", example=135.00),
     *                             @OA\Property(property="discounted_amount", type="number", example=15.00),
     *                             @OA\Property(property="payment_status", type="string", example="completed"),
     *                             @OA\Property(property="tran_ref", type="string", example="TST20250401ABC"),
     *                             @OA\Property(property="created_at", type="string", example="2025-04-01 14:00")
     *                         )
     *                     )
     *                 )
     *             )
     *         ),
     *         @OA\MediaType(mediaType="application/pdf", @OA\Schema(type="string", format="binary")),
     *         @OA\MediaType(mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", @OA\Schema(type="string", format="binary")),
     *         @OA\MediaType(mediaType="text/csv", @OA\Schema(type="string", format="binary"))
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Unsupported format",
     *         @OA\JsonContent(@OA\Property(property="error", type="string", example="Unsupported format. Use: json, pdf, excel, csv"))
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthenticated"))),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthorized")))
     * )
     */
    public function payments(Request $request)
    {
        $query = Payment::with(['user', 'listing', 'paymentMethod', 'promoCode'])
            ->when($request->filled('status'), fn($q) => $q->where('payment_status', $request->status))
            ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('listing_id'), fn($q) => $q->where('listing_id', $request->listing_id))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(fn($qq) => $qq->where('tran_ref', 'like', "%$s%")->orWhere('cart_id', 'like', "%$s%"));
            });

        $format = strtolower($request->get('format', 'json'));

        if ($format === 'json') {
            $payments = $query->paginate($request->get('per_page', 50));
            return response()->json(['success' => true, 'data' => $payments]);
        }

        $payments = $query->get();

        $columns = [
            'ID', 'User', 'Listing', 'Amount', 'Total Amount', 'Discount',
            'Status', 'Payment Method', 'Promo Code', 'Transaction Ref', 'Date',
        ];

        $rows = $payments->map(fn($p) => [
            $p->id,
            $p->user?->first_name . ' ' . $p->user?->last_name,
            $p->listing?->title ?? '—',
            $p->amount,
            $p->total_amount,
            $p->discounted_amount ?? 0,
            $p->payment_status,
            $p->paymentMethod?->name ?? '—',
            $p->promoCode?->code ?? '—',
            $p->tran_ref ?? '—',
            $p->created_at?->format('Y-m-d H:i'),
        ])->toArray();

        $title = 'Payments Report';
        $stats = [
            'Total Transactions' => $payments->count(),
            'Completed'          => $payments->where('payment_status', 'completed')->count(),
            'Pending'            => $payments->where('payment_status', 'pending')->count(),
            'Failed'             => $payments->where('payment_status', 'failed')->count(),
            'Total Revenue'      => number_format($payments->where('payment_status', 'completed')->sum('total_amount'), 2),
        ];

        return $this->export($format, 'payments_report', $title, $columns, $rows, $stats);
    }

    // ─────────────────────────────────────────────
    // SUMMARY REPORT
    // ─────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/admin/reporting/summary",
     *     summary="Global summary report (Users + Listings + Payments)",
     *     description="Aggregated statistics across users, listings, and payments. Optionally filtered by a date range. Supports JSON response or file download (PDF / Excel / CSV).",
     *     tags={"Admin - Reporting"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Response format",
     *         required=false,
     *         @OA\Schema(type="string", enum={"json","pdf","excel","csv"}, default="json", example="json")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Start of the period (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="End of the period (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-12-31")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Summary data (format=json) or file download (format=pdf|excel|csv)",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="success", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="data",
     *                     type="object",
     *                     @OA\Property(
     *                         property="period",
     *                         type="object",
     *                         @OA\Property(property="from", type="string", example="2025-01-01"),
     *                         @OA\Property(property="to", type="string", example="2025-12-31")
     *                     ),
     *                     @OA\Property(
     *                         property="users",
     *                         type="object",
     *                         @OA\Property(property="total", type="integer", example=500),
     *                         @OA\Property(property="active", type="integer", example=430),
     *                         @OA\Property(property="inactive", type="integer", example=70)
     *                     ),
     *                     @OA\Property(
     *                         property="listings",
     *                         type="object",
     *                         @OA\Property(property="total", type="integer", example=1200),
     *                         @OA\Property(property="published", type="integer", example=900),
     *                         @OA\Property(property="pending", type="integer", example=150),
     *                         @OA\Property(property="rejected", type="integer", example=80),
     *                         @OA\Property(property="draft", type="integer", example=70)
     *                     ),
     *                     @OA\Property(
     *                         property="payments",
     *                         type="object",
     *                         @OA\Property(property="total", type="integer", example=320),
     *                         @OA\Property(property="completed", type="integer", example=280),
     *                         @OA\Property(property="pending", type="integer", example=25),
     *                         @OA\Property(property="failed", type="integer", example=15),
     *                         @OA\Property(property="total_revenue", type="number", example=145000.00)
     *                     )
     *                 )
     *             )
     *         ),
     *         @OA\MediaType(mediaType="application/pdf", @OA\Schema(type="string", format="binary")),
     *         @OA\MediaType(mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", @OA\Schema(type="string", format="binary")),
     *         @OA\MediaType(mediaType="text/csv", @OA\Schema(type="string", format="binary"))
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Unsupported format",
     *         @OA\JsonContent(@OA\Property(property="error", type="string", example="Unsupported format. Use: json, pdf, excel, csv"))
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthenticated"))),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthorized")))
     * )
     */
    public function summary(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $usersQuery    = User::query();
        $listingsQuery = Listing::query();
        $paymentsQuery = Payment::query();

        if ($dateFrom) {
            $usersQuery->whereDate('created_at', '>=', $dateFrom);
            $listingsQuery->whereDate('created_at', '>=', $dateFrom);
            $paymentsQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $usersQuery->whereDate('created_at', '<=', $dateTo);
            $listingsQuery->whereDate('created_at', '<=', $dateTo);
            $paymentsQuery->whereDate('created_at', '<=', $dateTo);
        }

        $summary = [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'users' => [
                'total'    => $usersQuery->count(),
                'active'   => (clone $usersQuery)->where('is_active', true)->count(),
                'inactive' => (clone $usersQuery)->where('is_active', false)->count(),
            ],
            'listings' => [
                'total'     => $listingsQuery->count(),
                'published' => (clone $listingsQuery)->where('status', 'published')->count(),
                'pending'   => (clone $listingsQuery)->where('status', 'pending')->count(),
                'rejected'  => (clone $listingsQuery)->where('status', 'rejected')->count(),
                'draft'     => (clone $listingsQuery)->where('status', 'draft')->count(),
            ],
            'payments' => [
                'total'         => $paymentsQuery->count(),
                'completed'     => (clone $paymentsQuery)->where('payment_status', 'completed')->count(),
                'pending'       => (clone $paymentsQuery)->where('payment_status', 'pending')->count(),
                'failed'        => (clone $paymentsQuery)->where('payment_status', 'failed')->count(),
                'total_revenue' => (clone $paymentsQuery)->where('payment_status', 'completed')->sum('total_amount'),
            ],
        ];

        $format = strtolower($request->get('format', 'json'));

        if ($format === 'json') {
            return response()->json(['success' => true, 'data' => $summary]);
        }

        $title   = 'Summary Report';
        $columns = ['Metric', 'Value'];
        $rows    = [
            ['--- USERS ---', ''],
            ['Total Users', $summary['users']['total']],
            ['Active Users', $summary['users']['active']],
            ['Inactive Users', $summary['users']['inactive']],
            ['--- LISTINGS ---', ''],
            ['Total Listings', $summary['listings']['total']],
            ['Published', $summary['listings']['published']],
            ['Pending', $summary['listings']['pending']],
            ['Rejected', $summary['listings']['rejected']],
            ['Draft', $summary['listings']['draft']],
            ['--- PAYMENTS ---', ''],
            ['Total Transactions', $summary['payments']['total']],
            ['Completed', $summary['payments']['completed']],
            ['Pending', $summary['payments']['pending']],
            ['Failed', $summary['payments']['failed']],
            ['Total Revenue', number_format($summary['payments']['total_revenue'], 2)],
        ];

        return $this->export($format, 'summary_report', $title, $columns, $rows, []);
    }

    // ─────────────────────────────────────────────
    // SHARED EXPORT ENGINE
    // ─────────────────────────────────────────────

    private function export(string $format, string $filename, string $title, array $columns, array $rows, array $stats)
    {
        return match ($format) {
            'pdf'   => $this->exportPdf($filename, $title, $columns, $rows, $stats),
            'excel' => $this->exportExcel($filename, $columns, $rows, $stats, $title),
            'csv'   => $this->exportCsv($filename, $columns, $rows),
            default => response()->json(['error' => 'Unsupported format. Use: json, pdf, excel, csv'], 400),
        };
    }

    private function exportPdf(string $filename, string $title, array $columns, array $rows, array $stats)
    {
        $pdf = Pdf::loadView('reports.generic', compact('title', 'columns', 'rows', 'stats'))
            ->setPaper('a4', 'landscape')
            ->setOptions(['dpi' => 150, 'defaultFont' => 'sans-serif']);

        return $pdf->download($filename . '_' . now()->format('Ymd_His') . '.pdf');
    }

    private function exportExcel(string $filename, array $columns, array $rows, array $stats, string $title)
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');

        $colCount = \count($columns);
        $lastCol  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', $title . ' — Generated: ' . now()->format('Y-m-d H:i'));
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2D3D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $statsRow = 2;
        if (!empty($stats)) {
            $statText = collect($stats)->map(fn($v, $k) => "$k: $v")->implode('   |   ');
            $sheet->mergeCells("A2:{$lastCol}2");
            $sheet->setCellValue('A2', $statText);
            $sheet->getStyle('A2')->applyFromArray([
                'font'      => ['italic' => true, 'color' => ['rgb' => '555555']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F0F0']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $statsRow = 3;
        }

        $sheet->fromArray($columns, null, 'A' . $statsRow);
        $sheet->getStyle("A{$statsRow}:{$lastCol}{$statsRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3498DB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $dataStartRow = $statsRow + 1;
        foreach ($rows as $i => $row) {
            $rowNumber = $dataStartRow + $i;
            $sheet->fromArray($row, null, 'A' . $rowNumber);
            if ($i % 2 === 0) {
                $sheet->getStyle("A{$rowNumber}:{$lastCol}{$rowNumber}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EBF5FB']],
                ]);
            }
        }

        foreach (range(1, $colCount) as $colIndex) {
            $sheet->getColumnDimensionByColumn($colIndex)->setAutoSize(true);
        }

        $this->ensureTempDir();
        $path   = storage_path('app/temp/' . $filename . '_' . time() . '.xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return response()->download($path, $filename . '_' . now()->format('Ymd_His') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function exportCsv(string $filename, array $columns, array $rows)
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        $sheet->fromArray($columns, null, 'A1');
        foreach ($rows as $i => $row) {
            $sheet->fromArray($row, null, 'A' . ($i + 2));
        }

        $this->ensureTempDir();
        $path   = storage_path('app/temp/' . $filename . '_' . time() . '.csv');
        $writer = new Csv($spreadsheet);
        $writer->setDelimiter(',');
        $writer->setEnclosure('"');
        $writer->setLineEnding("\r\n");
        $writer->setUseBOM(true);
        $writer->save($path);

        return response()->download($path, $filename . '_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv',
        ])->deleteFileAfterSend(true);
    }

    private function ensureTempDir(): void
    {
        $dir = storage_path('app/temp');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
