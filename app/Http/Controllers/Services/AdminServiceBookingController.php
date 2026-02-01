<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\ServiceBooking;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Admin Service Bookings",
 *     description="API endpoints for managing service bookings (Admin)"
 * )
 */
class AdminServiceBookingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/bookings",
     *     summary="List all bookings (Admin)",
     *     operationId="adminGetBookings",
     *     tags={"Admin Service Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", description="Filter by status", @OA\Schema(type="string")),
     *     @OA\Parameter(name="provider_id", in="query", description="Filter by provider", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="user_id", in="query", description="Filter by user", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Bookings retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=12345),
     *                         @OA\Property(property="status", type="string", example="completed"),
     *                         @OA\Property(property="booking_date", type="string", format="date", example="2026-05-20"),
     *                         @OA\Property(property="total_amount", type="number", example=150.00),
     *                         @OA\Property(property="service", type="object", @OA\Property(property="name", type="string", example="Deep Cleaning")),
     *                         @OA\Property(property="user", type="object", @OA\Property(property="name", type="string", example="John Doe")),
     *                         @OA\Property(property="provider", type="object", @OA\Property(property="name", type="string", example="Cleaning Co."))
     *                     )
     *                 ),
     *                 @OA\Property(property="total", type="integer", example=85)
     *             ),
     *             @OA\Property(property="message", type="string", example="Bookings retrieved successfully")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = ServiceBooking::with(['service', 'provider.user', 'user', 'payment']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('provider_id')) {
            $query->where('provider_id', $request->provider_id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('date')) {
            $query->where('booking_date', $request->date);
        }

        $perPage = $request->input('per_page');

        if ($perPage) {
            $bookings = $query->orderBy('created_at', 'desc')->paginate($perPage);
        } else {
            $bookings = $query->orderBy('created_at', 'desc')->get();
        }

        return response()->json([
            'success' => true,
            'data' => $bookings,
            'message' => 'Bookings retrieved successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/bookings/{id}",
     *     summary="Get booking details (Admin)",
     *     operationId="adminGetBooking",
     *     tags={"Admin Service Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Booking details retrieved")
     * )
     */
    public function show($id)
    {
        $booking = ServiceBooking::with(['service', 'provider.user', 'user', 'payment', 'review', 'chatSession'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $booking,
            'message' => 'Booking details retrieved successfully'
        ]);
    }
}
