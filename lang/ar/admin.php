<?php

return [

    'locales' => [
        'ar' => 'العربية',
        'en' => 'الإنكليزية',
    ],

    'navigation' => [
        'catalog' => 'الكتالوج',
        'settings' => 'الإعدادات',
    ],

    'resources' => [
        'category' => 'فئة',
        'categories' => 'الفئات',
        'product' => 'منتج',
        'products' => 'المنتجات',
    ],

    'fields' => [
        'name' => 'الاسم',
        'name_ar' => 'الاسم (عربي)',
        'name_en' => 'الاسم (إنكليزي)',
        'slug' => 'الرابط المختصر',
        'description' => 'الوصف',
        'image' => 'الصورة',
        'images' => 'الصور',
        'alt' => 'النص البديل',
        'is_primary' => 'الصورة الرئيسية',
        'is_active' => 'منشور',
        'is_featured' => 'مميّز',
        'sort_order' => 'ترتيب العرض',
        'category' => 'الفئة',
        'sku' => 'رمز المنتج',
        'products_count' => 'المنتجات',
        'updated_at' => 'آخر تعديل',
        'created_at' => 'تاريخ الإنشاء',
    ],

    'category' => [
        'media' => 'صورة الفئة',
        'publishing' => 'النشر',
    ],

    'product' => [
        'classification' => 'الفئة والرمز',
        'media' => 'صور المنتج',
        'publishing' => 'النشر',
    ],

    'settings' => [
        'title' => 'إعدادات الموقع',
        'identity' => 'الهوية',
        'hero' => 'واجهة الصفحة الرئيسية',
        'contact' => 'بيانات التواصل',
        'social' => 'روابط التواصل الاجتماعي',
        'seo' => 'إعدادات محركات البحث',
        'save' => 'حفظ الإعدادات',
        'saved' => 'تم حفظ الإعدادات.',
        'site_name' => 'اسم الموقع',
        'logo' => 'الشعار',
        'hero_title' => 'العنوان الرئيسي',
        'hero_subtitle' => 'العنوان الفرعي',
        'footer_text' => 'نص التذييل',
        'meta_description' => 'الوصف الافتراضي للصفحات',
        'contact_email' => 'البريد الإلكتروني',
        'contact_phone' => 'الهاتف',
        'contact_address' => 'العنوان',
        'facebook' => 'رابط فيسبوك',
        'instagram' => 'رابط إنستغرام',
        'whatsapp' => 'رابط واتساب',
    ],

    'hints' => [
        'image' => 'الصيغ المقبولة: :types، وبحد أقصى :size ميغابايت.',
        'slug' => 'يُستخدم في رابط اللغة :locale. حروف وأرقام وشرطات فقط.',
        'category_is_active' => 'إلغاء نشر الفئة يخفي منتجاتها أيضاً عن واجهة الزوار.',
        'product_is_active' => 'لا يظهر المنتج للزوار إلا إذا كان منشوراً وكانت فئته منشورة.',
        'primary_image' => 'صورة رئيسية واحدة فقط، وهي التي تظهر في القوائم.',
        'sku_unique' => 'اختياري، لكنه يجب أن يكون فريداً عند إدخاله.',
    ],

    'validation' => [
        'slug' => 'يسمح الرابط المختصر بالحروف والأرقام والشرطات المفردة فقط.',
        'single_primary' => 'يمكن تحديد صورة رئيسية واحدة فقط.',
        'primary_required' => 'حدّد صورة رئيسية واحدة.',
    ],

    'notifications' => [
        'category_has_products' => 'لا تزال هذه الفئة تحتوي على منتجات. انقلها أو احذفها أولاً.',
    ],

    'dashboard' => [
        'categories_total' => 'الفئات',
        'categories_published' => 'الفئات المنشورة',
        'products_total' => 'المنتجات',
        'products_published' => 'المنتجات المنشورة',
        'products_featured' => 'المنتجات المميزة',
        'images_total' => 'صور المنتجات',
        'published_of_total' => 'منشور :published من :total',
        'unpublished_count' => ':count غير منشور',
        'recent_updates' => 'آخر التعديلات',
        'type' => 'النوع',
        'no_recent' => 'لم يجرِ تعديل أي شيء بعد.',
        'view_site' => 'عرض واجهة الزوار',
    ],

];
