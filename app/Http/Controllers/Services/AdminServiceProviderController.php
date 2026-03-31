<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Admin Service Providers",
 *     description="API endpoints for managing service providers (Admin)"
 * )
 */
class AdminServiceProviderController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/service-providers",
     *     summary="List all service providers (Admin)",
     *     operationId="adminGetProviders",
     *     tags={"Admin Service Providers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="is_verified", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $query = ServiceProvider::with(['user', 'city', 'country', 'activeSubscription.plan']);

        if ($request->has('is_verified')) {
            $query->where('is_verified', $request->boolean('is_verified'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('business_name', 'LIKE', "%{$search}%")
                  ->orWhere('business_name_ar', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $providers = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $providers,
            'message' => 'Service providers retrieved successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/service-providers/{id}",
     *     summary="Get service provider details (Admin)",
     *     operationId="adminGetProvider",
     *     tags={"Admin Service Providers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function show($id)
    {
        $provider = ServiceProvider::with([
            'user', 
            'city', 
            'country', 
            'services.category', 
            'activeSubscription.plan', 
            'images', 
            'workingHours'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $provider,
            'message' => 'Service provider details retrieved successfully'
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/service-providers/{id}",
     *     summary="Update service provider details (Admin)",
     *     operationId="adminUpdateProvider",
     *     tags={"Admin Service Providers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function update(Request $request, $id)
    {
        $provider = ServiceProvider::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'business_name' => 'string|max:255',
            'business_name_ar' => 'string|max:255',
            'email' => 'email|max:255',
            'phone' => 'string|max:20',
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
            'city_id' => 'exists:cities,id',
            'country_id' => 'exists:countries,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $provider->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $provider->fresh(['user', 'city', 'country']),
            'message' => 'Service provider updated successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/service-providers/{id}/verify",
     *     summary="Verify a service provider (Admin)",
     *     operationId="adminVerifyProvider",
     *     tags={"Admin Service Providers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function verify($id)
    {
        $provider = ServiceProvider::findOrFail($id);
        $provider->update(['is_verified' => true]);

        return response()->json([
            'success' => true,
            'data' => $provider,
            'message' => 'Service provider verified successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/service-providers/{id}/toggle-status",
     *     summary="Toggle service provider active status (Admin)",
     *     operationId="adminToggleProviderStatus",
     *     tags={"Admin Service Providers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function toggleStatus($id)
    {
        $provider = ServiceProvider::findOrFail($id);
        $provider->update(['is_active' => !$provider->is_active]);

        return response()->json([
            'success' => true,
            'data' => $provider,
            'message' => 'Service provider status toggled successfully'
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/service-providers/{id}",
     *     summary="Delete a service provider (Admin)",
     *     operationId="adminDeleteProvider",
     *     tags={"Admin Service Providers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function destroy($id)
    {
        $provider = ServiceProvider::findOrFail($id);
        $provider->delete();

        return response()->json([
            'success' => true,
            'message' => 'Service provider deleted successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/service-providers/stats",
     *     summary="Get service provider statistics (Admin)",
     *     operationId="adminGetProviderStats",
     *     tags={"Admin Service Providers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function stats()
    {
        $total = ServiceProvider::count();
        $verified = ServiceProvider::where('is_verified', true)->count();
        $active = ServiceProvider::where('is_active', true)->count();
        
        $byCity = ServiceProvider::select('city_id', DB::raw('count(*) as count'))
            ->groupBy('city_id')
            ->with('city:id,name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_providers' => $total,
                'verified_providers' => $verified,
                'active_providers' => $active,
                'by_city' => $byCity
            ],
            'message' => 'Service provider statistics retrieved successfully'
        ]);
    }
}
