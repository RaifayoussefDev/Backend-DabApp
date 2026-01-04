<?php

return [
    'singular' => 'دليل',
    'plural' => 'أدلة',
    'menu_title' => 'إدارة الأدلة',

    // Actions
    'create' => 'إنشاء دليل',
    'edit' => 'تعديل الدليل',
    'show' => 'عرض الدليل',
    'delete' => 'حذف الدليل',
    'save' => 'حفظ',
    'update' => 'تحديث',
    'cancel' => 'إلغاء',
    'back' => 'عودة',
    'search' => 'بحث',
    'filter' => 'تصفية',

    // Attributes
    'id' => 'معرف',
    'title' => 'العنوان',
    'slug' => 'الرابط الدائم',
    'content' => 'المحتوى',
    'excerpt' => 'ملخص',
    'featured_image' => 'الصورة البارزة',
    'category' => 'القسم',
    'category_id' => 'القسم',
    'author' => 'الكاتب',
    'author_id' => 'الكاتب',
    'status' => 'الحالة',
    'is_featured' => 'مميز',
    'views_count' => 'عدد المشاهدات',
    'published_at' => 'تاريخ النشر',
    'created_at' => 'تاريخ الإنشاء',
    'updated_at' => 'تاريخ التحديث',
    'deleted_at' => 'تاريخ الحذف',

    // Relations
    'sections' => 'أقسام الدليل',
    'comments' => 'التعليقات',
    'tags' => 'الوسوم',
    'images' => 'الصور',
    'likes' => 'الإعجابات',
    'bookmarks' => 'المحفوظات',

    // Sections
    'section' => [
        'title' => 'عنوان القسم',
        'description' => 'وصف القسم',
        'image' => 'صورة القسم',
        'order' => 'الترتيب',
        'type' => 'النوع',
        'media' => 'الوسائط',
        'image_position' => 'موضع الصورة',
        'add' => 'إضافة قسم',
        'remove' => 'حذف القسم',
    ],

    // Enums / Status
    'statuses' => [
        'draft' => 'مسودة',
        'published' => 'منشور',
        'archived' => 'مؤرشف',
        'pending' => 'قيد الانتظار',
        'rejected' => 'مرفوض',
    ],

    // Messages
    'messages' => [
        'created' => 'تم إنشاء الدليل بنجاح',
        'updated' => 'تم تحديث الدليل بنجاح',
        'deleted' => 'تم حذف الدليل بنجاح',
        'restored' => 'تم استعادة الدليل بنجاح',
        'not_found' => 'الدليل غير موجود',
        'status_updated' => 'تم تحديث حالة الدليل',
    ],
];
