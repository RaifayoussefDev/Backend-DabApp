<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Trainer profile engagement: like / favorite / approved comment notify the trainer.
 */
return new class extends Migration
{
    private function templates(): array
    {
        return [
            'trainer_new_like' => [
                'name' => 'Trainer New Like',
                'title' => 'New like',
                'title_ar' => 'إعجاب جديد',
                'message' => '{user_name} liked your trainer profile.',
                'message_ar' => 'أعجب {user_name} بملفك كمدرب.',
                'icon' => 'favorite',
                'color' => '#E91E63',
                'variables' => ['user_name'],
            ],
            'trainer_new_favorite' => [
                'name' => 'Trainer New Favorite',
                'title' => 'Added to favorites',
                'title_ar' => 'تمت إضافتك إلى المفضلة',
                'message' => '{user_name} added you to their favorite trainers.',
                'message_ar' => 'أضافك {user_name} إلى مدربيه المفضلين.',
                'icon' => 'star',
                'color' => '#FFC107',
                'variables' => ['user_name'],
            ],
            'trainer_new_comment' => [
                'name' => 'Trainer New Comment',
                'title' => 'New comment on your profile',
                'title_ar' => 'تعليق جديد على ملفك',
                'message' => '{user_name} commented: "{comment}"',
                'message_ar' => 'علّق {user_name}: "{comment}"',
                'icon' => 'comment',
                'color' => '#2196F3',
                'variables' => ['user_name', 'comment'],
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->templates() as $type => $t) {
            DB::table('notification_templates')->updateOrInsert(
                ['type' => $type],
                [
                    'name' => $t['name'],
                    'title_template' => $t['title'],
                    'title_template_ar' => $t['title_ar'],
                    'message_template' => $t['message'],
                    'message_template_ar' => $t['message_ar'],
                    'icon' => $t['icon'],
                    'color' => $t['color'],
                    'is_active' => true,
                    'variables' => json_encode($t['variables']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')->whereIn('type', array_keys($this->templates()))->delete();
    }
};
