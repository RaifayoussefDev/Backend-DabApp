<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\BannerView;
use App\Models\BannerClick;
use App\Models\AdSubmission;
use Illuminate\Http\JsonResponse;

class AdminBannerStatsController extends Controller
{
    /**
     * GET /api/admin/banners/{id}/stats
     * Global stats for a banner: views, clicks, submissions, rates.
     */
    public function stats($id): JsonResponse
    {
        $banner = Banner::find($id);
        if (!$banner) {
            return response()->json(['success' => false, 'message' => 'Banner not found'], 404);
        }

        // If this is a visual banner linked to an ad, use the ad's ID for tracking lookup
        $trackingId = $banner->ad_id ?? $banner->id;

        $totalViews       = BannerView::where('banner_id', $trackingId)->count();
        $uniqueViews      = BannerView::where('banner_id', $trackingId)->whereNotNull('user_id')->distinct('user_id')->count('user_id');
        $totalClicks      = BannerClick::where('banner_id', $trackingId)->count();
        $totalSubmissions = AdSubmission::where('banner_id', $trackingId)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'banner_id'         => (int) $id,
                'ad_id'             => $trackingId,
                'banner_title'      => $banner->title,
                'total_views'       => $totalViews,
                'unique_views'      => $uniqueViews,
                'total_clicks'      => $totalClicks,
                'total_submissions' => $totalSubmissions,
                'click_rate'        => $totalViews > 0 ? round(($totalClicks / $totalViews) * 100, 2) . '%' : '0%',
                'conversion_rate'   => $totalViews > 0 ? round(($totalSubmissions / $totalViews) * 100, 2) . '%' : '0%',
            ],
        ]);
    }

    /**
     * GET /api/admin/banners/{id}/views
     * List of users who viewed the banner (paginated).
     */
    public function views($id): JsonResponse
    {
        $banner = Banner::find($id);
        if (!$banner) {
            return response()->json(['success' => false, 'message' => 'Banner not found'], 404);
        }

        $trackingId = $banner->ad_id ?? $banner->id;

        $views = BannerView::where('banner_id', $trackingId)
            ->with('user:id,first_name,last_name,email,phone')
            ->orderByDesc('viewed_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $views]);
    }

    /**
     * GET /api/admin/banners/{id}/clicks
     * List of users who clicked the banner CTA (paginated).
     */
    public function clicks($id): JsonResponse
    {
        $banner = Banner::find($id);
        if (!$banner) {
            return response()->json(['success' => false, 'message' => 'Banner not found'], 404);
        }

        $trackingId = $banner->ad_id ?? $banner->id;

        $clicks = BannerClick::where('banner_id', $trackingId)
            ->with('user:id,first_name,last_name,email,phone')
            ->orderByDesc('clicked_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $clicks]);
    }

    /**
     * GET /api/admin/banners/overview
     * Overview of all banners with their view/click/submission counts.
     */
    public function overview(): JsonResponse
    {
        $banners = Banner::withCount([
            'adSubmissions as total_submissions',
        ])
            ->orderByDesc('total_submissions')
            ->get()
            ->map(function ($banner) {
                $totalViews  = BannerView::where('banner_id', $banner->id)->count();
                $totalClicks = BannerClick::where('banner_id', $banner->id)->count();

                return [
                    'id'                => $banner->id,
                    'title'             => $banner->title,
                    'is_active'         => $banner->is_active,
                    'total_views'       => $totalViews,
                    'total_clicks'      => $totalClicks,
                    'total_submissions' => $banner->total_submissions,
                    'click_rate'        => $totalViews > 0 ? round(($totalClicks / $totalViews) * 100, 2) . '%' : '0%',
                ];
            });

        return response()->json(['success' => true, 'data' => $banners]);
    }
}
