<?php

namespace App\Http\Controllers;

use App\Models\ContactInfo;
use Illuminate\Http\Request;

class AdminContactInfoController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/contact-info",
     *     summary="Get site contact info (Admin)",
     *     tags={"Admin Contact Info"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Contact info retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="address_en", type="string", example="King Fahd Road, Riyadh, Saudi Arabia"),
     *             @OA\Property(property="address_ar", type="string", example="طريق الملك فهد، الرياض، المملكة العربية السعودية"),
     *             @OA\Property(property="phone", type="string", example="+966 12 345 6789"),
     *             @OA\Property(property="email", type="string", example="support@dabapp.co"),
     *             @OA\Property(property="hours_en", type="string", example="Sun - Thu: 9:00 AM - 6:00 PM"),
     *             @OA\Property(property="hours_ar", type="string", example="الأحد - الخميس: 9:00 صباحاً - 6:00 مساءً")
     *         )
     *     )
     * )
     */
    public function show()
    {
        $contactInfo = ContactInfo::first();
        if (!$contactInfo) {
            $contactInfo = ContactInfo::create([
                'address_en' => '',
                'address_ar' => '',
                'phone' => '',
                'email' => '',
                'hours_en' => '',
                'hours_ar' => '',
            ]);
        }
        return response()->json($contactInfo);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/contact-info",
     *     summary="Update site contact info (Admin)",
     *     tags={"Admin Contact Info"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="<strong>Example payload</strong><br><pre>{<br>  &quot;address_en&quot;: &quot;King Fahd Road, Riyadh, Saudi Arabia&quot;,<br>  &quot;address_ar&quot;: &quot;طريق الملك فهد، الرياض، المملكة العربية السعودية&quot;,<br>  &quot;phone&quot;: &quot;+966 12 345 6789&quot;,<br>  &quot;email&quot;: &quot;support@dabapp.co&quot;,<br>  &quot;hours_en&quot;: &quot;Sun - Thu: 9:00 AM - 6:00 PM&quot;,<br>  &quot;hours_ar&quot;: &quot;الأحد - الخميس: 9:00 صباحاً - 6:00 مساءً&quot;<br>}</pre>",
     *         @OA\JsonContent(
     *             required={"address_en", "address_ar", "phone", "email", "hours_en", "hours_ar"},
     *             @OA\Property(property="address_en", type="string", example="King Fahd Road, Riyadh, Saudi Arabia"),
     *             @OA\Property(property="address_ar", type="string", example="طريق الملك فهد، الرياض، المملكة العربية السعودية"),
     *             @OA\Property(property="phone", type="string", example="+966 12 345 6789"),
     *             @OA\Property(property="email", type="string", example="support@dabapp.co"),
     *             @OA\Property(property="hours_en", type="string", example="Sun - Thu: 9:00 AM - 6:00 PM"),
     *             @OA\Property(property="hours_ar", type="string", example="الأحد - الخميس: 9:00 صباحاً - 6:00 مساءً"),
     *             @OA\Property(property="address_visible", type="boolean", example=true, description="Show/hide the address block on the public page"),
     *             @OA\Property(property="phone_visible", type="boolean", example=true),
     *             @OA\Property(property="email_visible", type="boolean", example=true),
     *             @OA\Property(property="hours_visible", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contact info updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Contact info updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'address_en' => 'required|string|max:255',
            'address_ar' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'hours_en' => 'required|string|max:255',
            'hours_ar' => 'required|string|max:255',
            'address_visible' => 'nullable|boolean',
            'phone_visible' => 'nullable|boolean',
            'email_visible' => 'nullable|boolean',
            'hours_visible' => 'nullable|boolean',
        ]);

        $contactInfo = ContactInfo::first();

        if (!$contactInfo) {
            $contactInfo = ContactInfo::create($validated);
        } else {
            $contactInfo->update($validated);
        }

        return response()->json([
            'message' => 'Contact info updated successfully',
            'data' => $contactInfo
        ]);
    }
}
