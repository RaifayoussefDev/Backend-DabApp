<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminAppVersionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/app-version",
     *     summary="Get App Version Configuration (Admin)",
     *     tags={"Admin App Version"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="App version configuration retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="latest_version", type="string", example="1.1.9"),
     *             @OA\Property(property="min_supported_version", type="string", example="1.1.7"),
     *             @OA\Property(property="store_url_android", type="string", example="https://play.google.com..."),
     *             @OA\Property(property="store_url_ios", type="string", example="https://apps.apple.com..."),
     *             @OA\Property(property="force", type="integer", example=0)
     *         )
     *     )
     * )
     */
    public function show()
    {
        $appVersion = \App\Models\AppVersion::first();
        if (!$appVersion) {
            $appVersion = \App\Models\AppVersion::create([
                'latest_version' => '1.0.0',
                'min_supported_version' => '1.0.0',
                'store_url_android' => '',
                'store_url_ios' => '',
                'force' => false
            ]);
        }
        return response()->json($appVersion);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/app-version",
     *     summary="Update App Version Configuration (Admin)",
     *     tags={"Admin App Version"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="<strong>Example payload</strong><br><pre>{<br>  &quot;latest_version&quot;: &quot;1.1.9&quot;,<br>  &quot;min_supported_version&quot;: &quot;1.1.7&quot;,<br>  &quot;store_url_android&quot;: &quot;https://play.google.com/store/apps/details?id=com.dabapp&quot;,<br>  &quot;store_url_ios&quot;: &quot;https://apps.apple.com/app/dabapp/id123456789&quot;,<br>  &quot;force&quot;: false<br>}</pre>",
     *         @OA\JsonContent(
     *             required={"latest_version", "min_supported_version", "force"},
     *             @OA\Property(property="latest_version", type="string", example="1.1.9"),
     *             @OA\Property(property="min_supported_version", type="string", example="1.1.7"),
     *             @OA\Property(property="store_url_android", type="string", example="https://play.google.com/store/apps/details?id=com.dabapp"),
     *             @OA\Property(property="store_url_ios", type="string", example="https://apps.apple.com/app/dabapp/id123456789"),
     *             @OA\Property(property="force", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="App version configuration updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="App version updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'latest_version' => 'required|string',
            'min_supported_version' => 'required|string',
            'store_url_android' => 'nullable|string',
            'store_url_ios' => 'nullable|string',
            'force' => 'required|boolean',
        ]);

        $appVersion = \App\Models\AppVersion::first();

        if (!$appVersion) {
            $appVersion = \App\Models\AppVersion::create($validated);
        } else {
            $appVersion->update($validated);
        }

        return response()->json([
            'message' => 'App version updated successfully',
            'data' => $appVersion
        ]);
    }
}
