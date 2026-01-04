<?php

namespace Database\Seeders;

use App\Models\ReportReason;
use Illuminate\Database\Seeder;

class ReportReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reasons = [
            'default' => [
                ['label_en' => 'Spam', 'label_ar' => 'محتوى غير مرغوب فيه'],
                ['label_en' => 'Inappropriate Content', 'label_ar' => 'محتوى غير لائق'],
                ['label_en' => 'Other', 'label_ar' => 'أخرى'],
            ],
            'guide' => [
                ['label_en' => 'Misinformation', 'label_ar' => 'معلومات خاطئة'],
                ['label_en' => 'Copyright Violation', 'label_ar' => 'انتهاك حقوق النشر'],
                ['label_en' => 'Spam', 'label_ar' => 'محتوى غير مرغوب فيه'],
                ['label_en' => 'Other', 'label_ar' => 'أخرى'],
            ],
            'listing' => [
                 ['label_en' => 'Already Sold', 'label_ar' => 'تم البيع'],
                 ['label_en' => 'Fake Listing', 'label_ar' => 'إعلان وهمي'],
                 ['label_en' => 'Wrong Pricing', 'label_ar' => 'سعر غير صحيح'],
                 ['label_en' => 'Other', 'label_ar' => 'أخرى'],
            ],
            'event' => [
                ['label_en' => 'Event Cancelled', 'label_ar' => 'تم إلغاء الحدث'],
                ['label_en' => 'Spam', 'label_ar' => 'محتوى غير مرغوب فيه'],
                ['label_en' => 'Other', 'label_ar' => 'أخرى'],
            ],
            'comment' => [
                ['label_en' => 'Harassment', 'label_ar' => 'مضايقة'],
                ['label_en' => 'Spam', 'label_ar' => 'محتوى غير مرغوب فيه'],
                ['label_en' => 'Other', 'label_ar' => 'أخرى'],
            ]
        ];

        foreach ($reasons as $code => $items) {
            $reportType = \App\Models\ReportType::where('code', $code)->first();
            
            if (!$reportType) continue;

            foreach ($items as $item) {
                ReportReason::firstOrCreate([
                    'report_type_id' => $reportType->id,
                    'label_en' => $item['label_en'],
                ], [
                    'label_ar' => $item['label_ar'],
                    'is_active' => true,
                ]);
            }
        }
    }
}
