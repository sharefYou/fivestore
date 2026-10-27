<?php

return [

    'locales' => [
        'ar' => 'Arabic',
        'en' => 'English',
    ],

    'navigation' => [
        'catalog' => 'Catalog',
        'settings' => 'Settings',
    ],

    'resources' => [
        'category' => 'Category',
        'categories' => 'Categories',
        'product' => 'Product',
        'products' => 'Products',
    ],

    'fields' => [
        'name' => 'Name',
        'name_ar' => 'Name (Arabic)',
        'name_en' => 'Name (English)',
        'slug' => 'URL slug',
        'description' => 'Description',
        'image' => 'Image',
        'images' => 'Images',
        'alt' => 'Alternative text',
        'is_primary' => 'Primary image',
        'is_active' => 'Published',
        'is_featured' => 'Featured',
        'sort_order' => 'Display order',
        'category' => 'Category',
        'sku' => 'SKU',
        'price' => 'Price',
        'products_count' => 'Products',
        'updated_at' => 'Last updated',
        'created_at' => 'Created',
    ],

    'category' => [
        'media' => 'Category image',
        'publishing' => 'Publishing',
    ],

    'product' => [
        'classification' => 'Category and code',
        'media' => 'Product images',
        'publishing' => 'Publishing',
    ],

    'settings' => [
        'title' => 'Site settings',
        'identity' => 'Identity',
        'hero' => 'Home page hero',
        'contact' => 'Contact details',
        'social' => 'Social links',
        'seo' => 'Search engine defaults',
        'save' => 'Save settings',
        'saved' => 'Settings saved.',
        'site_name' => 'Site name',
        'logo' => 'Logo',
        'hero_title' => 'Hero title',
        'hero_subtitle' => 'Hero subtitle',
        'footer_text' => 'Footer text',
        'meta_description' => 'Default meta description',
        'contact_email' => 'Email',
        'contact_phone' => 'Phone',
        'contact_address' => 'Address',
        'facebook' => 'Facebook URL',
        'instagram' => 'Instagram URL',
        'whatsapp' => 'WhatsApp URL',
    ],

    'hints' => [
        'image' => 'Accepted: :types, up to :size MB.',
        'slug' => 'Used in the :locale URL. Letters, numbers and hyphens only.',
        'category_is_active' => 'Unpublishing a category also hides its products from the storefront.',
        'product_is_active' => 'A product is public only when both it and its category are published.',
        'primary_image' => 'Exactly one image can be primary; it is the one shown in listings.',
        'sku_unique' => 'Optional, but must be unique when set.',
        'price' => 'Optional. Leave empty for a product with no published price; 0 would read as free.',
    ],

    'validation' => [
        'slug' => 'The slug may contain letters, numbers and single hyphens only.',
        'single_primary' => 'Only one image can be marked as primary.',
        'primary_required' => 'Mark one image as the primary image.',
    ],

    'notifications' => [
        'category_has_products' => 'This category still has products. Move or delete them first.',
    ],

    'dashboard' => [
        'categories_total' => 'Categories',
        'categories_published' => 'Published categories',
        'products_total' => 'Products',
        'products_published' => 'Published products',
        'products_featured' => 'Featured products',
        'images_total' => 'Product images',
        'published_of_total' => ':published of :total published',
        'unpublished_count' => ':count unpublished',
        'recent_updates' => 'Recently updated',
        'type' => 'Type',
        'no_recent' => 'Nothing has been edited yet.',
        'view_site' => 'View storefront',
    ],

];
