<?php

namespace App\Http\Controllers;

use App\Models\ContactInfo;
use App\Models\ContactInfoItem;

class ContactInfoController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/contact-info",
     *     summary="Get site contact info (address, phone, email, working hours, plus admin-added extra items)",
     *     description="The 4 fixed fields are each omitted (null) when their *_visible flag is off. `items` is the admin-managed list of extra contact channels (e.g. WhatsApp) — only active ones, in display order.",
     *     tags={"Contact Info"},
     *     @OA\Response(
     *         response=200,
     *         description="Contact info retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="address_en", type="string", nullable=true, example="King Fahd Road, Riyadh, Saudi Arabia"),
     *             @OA\Property(property="address_ar", type="string", nullable=true, example="طريق الملك فهد، الرياض، المملكة العربية السعودية"),
     *             @OA\Property(property="phone", type="string", nullable=true, example="+966 12 345 6789"),
     *             @OA\Property(property="email", type="string", nullable=true, example="support@dabapp.co"),
     *             @OA\Property(property="hours_en", type="string", nullable=true, example="Sun - Thu: 9:00 AM - 6:00 PM"),
     *             @OA\Property(property="hours_ar", type="string", nullable=true, example="الأحد - الخميس: 9:00 صباحاً - 6:00 مساءً"),
     *             @OA\Property(property="items", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="icon", type="string", nullable=true, example="whatsapp"),
     *                 @OA\Property(property="label_en", type="string"),
     *                 @OA\Property(property="label_ar", type="string", nullable=true),
     *                 @OA\Property(property="value_en", type="string"),
     *                 @OA\Property(property="value_ar", type="string", nullable=true)
     *             ))
     *         )
     *     )
     * )
     */
    public function index()
    {
        $contactInfo = ContactInfo::first();
        $items = ContactInfoItem::active()->ordered()->get(['id', 'icon', 'label_en', 'label_ar', 'value_en', 'value_ar']);

        if (!$contactInfo) {
            return response()->json([
                'address_en' => null,
                'address_ar' => null,
                'phone' => null,
                'email' => null,
                'hours_en' => null,
                'hours_ar' => null,
                'items' => $items,
            ]);
        }

        return response()->json([
            'address_en' => $contactInfo->address_visible ? $contactInfo->address_en : null,
            'address_ar' => $contactInfo->address_visible ? $contactInfo->address_ar : null,
            'phone' => $contactInfo->phone_visible ? $contactInfo->phone : null,
            'email' => $contactInfo->email_visible ? $contactInfo->email : null,
            'hours_en' => $contactInfo->hours_visible ? $contactInfo->hours_en : null,
            'hours_ar' => $contactInfo->hours_visible ? $contactInfo->hours_ar : null,
            'items' => $items,
        ]);
    }
}
