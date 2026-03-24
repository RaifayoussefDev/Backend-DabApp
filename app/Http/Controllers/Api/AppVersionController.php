<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AppVersionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/app-version",
     *     summary="Get App Version Configuration",
     *     tags={"App Version"},
     *     @OA\Response(
     *         response=200,
     *         description="App version configuration retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="latest_version", type="string", example="1.1.9"),
     *             @OA\Property(property="min_supported_version", type="string", example="1.1.7"),
     *             @OA\Property(property="store_url_android", type="string", example="https://play.google.com/store/apps/details?id=com.dabapp"),
     *             @OA\Property(property="store_url_ios", type="string", example="https://apps.apple.com/app/dabapp/id123456789"),
     *             @OA\Property(property="force", type="boolean", example=false)
     *         )
     *     )
     * )
     */
    public function index()
    {
        $appVersion = \App\Models\AppVersion::first();

        if (!$appVersion) {
            return response()->json([
                'latest_version' => '1.0.0',
                'min_supported_version' => '1.0.0',
                'store_url_android' => '',
                'store_url_ios' => '',
                'force' => false
            ]);
        }

        return response()->json([
            'latest_version' => $appVersion->latest_version,
            'min_supported_version' => $appVersion->min_supported_version,
            'store_url_android' => $appVersion->store_url_android,
            'store_url_ios' => $appVersion->store_url_ios,
            'force' => (bool)$appVersion->force,
        ]);
    }
}
