<?php

namespace App\Http\Controllers;

use App\Models\ContactInfo;

class ContactInfoController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/contact-info",
     *     summary="Get site contact info (address, phone, email, working hours)",
     *     tags={"Contact Info"},
     *     @OA\Response(
     *         response=200,
     *         description="Contact info retrieved",
     *         @OA\JsonContent(
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
    public function index()
    {
        $contactInfo = ContactInfo::first();

        if (!$contactInfo) {
            return response()->json([
                'address_en' => '',
                'address_ar' => '',
                'phone' => '',
                'email' => '',
                'hours_en' => '',
                'hours_ar' => '',
            ]);
        }

        return response()->json([
            'address_en' => $contactInfo->address_en,
            'address_ar' => $contactInfo->address_ar,
            'phone' => $contactInfo->phone,
            'email' => $contactInfo->email,
            'hours_en' => $contactInfo->hours_en,
            'hours_ar' => $contactInfo->hours_ar,
        ]);
    }
}
