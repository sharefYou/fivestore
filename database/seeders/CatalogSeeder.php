<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SiteSetting;
use App\Services\SlugGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo catalog for local development and manual testing.
 *
 * The data is deliberately varied: unpublished rows and an unpublished
 * category exist so the publishing rules can be seen working on a running
 * site, not only in the test suite.
 */
class CatalogSeeder extends Seeder
{
    /**
     * @var array<int, array{ar: string, en: string, published: bool, products: array<int, array{ar: string, en: string, published?: bool, featured?: bool}>}>
     */
    protected array $catalog = [
        [
            'ar' => 'الأثاث المنزلي',
            'en' => 'Home Furniture',
            'published' => true,
            'products' => [
                ['ar' => 'كرسي خشبي كلاسيكي', 'en' => 'Classic Wooden Chair', 'featured' => true],
                ['ar' => 'طاولة طعام عائلية', 'en' => 'Family Dining Table', 'featured' => true],
                ['ar' => 'رف كتب جداري', 'en' => 'Wall Bookshelf'],
                ['ar' => 'خزانة ملابس بثلاثة أبواب', 'en' => 'Three Door Wardrobe'],
                ['ar' => 'كنبة قماشية', 'en' => 'Fabric Sofa', 'published' => false],
            ],
        ],
        [
            'ar' => 'الإضاءة',
            'en' => 'Lighting',
            'published' => true,
            'products' => [
                ['ar' => 'مصباح طاولة نحاسي', 'en' => 'Brass Table Lamp', 'featured' => true],
                ['ar' => 'ثُريا معلّقة', 'en' => 'Hanging Chandelier'],
                ['ar' => 'إضاءة حائط حديثة', 'en' => 'Modern Wall Light'],
            ],
        ],
        [
            'ar' => 'مستلزمات المطبخ',
            'en' => 'Kitchenware',
            'published' => true,
            'products' => [
                ['ar' => 'طقم أواني ستانلس', 'en' => 'Stainless Cookware Set', 'featured' => true],
                ['ar' => 'إبريق قهوة عربي', 'en' => 'Arabic Coffee Pot'],
                ['ar' => 'لوح تقطيع خشبي', 'en' => 'Wooden Cutting Board'],
            ],
        ],
        [
            'ar' => 'مجموعة الموسم القادم',
            'en' => 'Next Season Collection',
            'published' => false,
            'products' => [
                ['ar' => 'طقم سجاد صوفي', 'en' => 'Wool Rug Set'],
            ],
        ],
    ];

    public function run(SlugGenerator $slugs): void
    {
        $this->seedSettings();

        foreach ($this->catalog as $index => $group) {
            DB::transaction(function () use ($group, $index, $slugs): void {
                $category = Category::query()->create([
                    'name_ar' => $group['ar'],
                    'name_en' => $group['en'],
                    'description_ar' => 'مجموعة مختارة من '.$group['ar'].' المتوفرة في الكتالوج.',
                    'description_en' => 'A selected range of '.strtolower($group['en']).' available in the catalog.',
                    'slug_ar' => $slugs->unique(Category::class, 'slug_ar', $group['ar'], 'ar'),
                    'slug_en' => $slugs->unique(Category::class, 'slug_en', $group['en'], 'en'),
                    'image_path' => $this->placeholder('categories', $group['en'], 1200, 675),
                    'is_active' => $group['published'],
                    'sort_order' => $index * 10,
                ]);

                foreach ($group['products'] as $position => $item) {
                    $product = Product::query()->create([
                        'category_id' => $category->id,
                        'name_ar' => $item['ar'],
                        'name_en' => $item['en'],
                        'description_ar' => 'وصف تجريبي لـ'.$item['ar'].'. يمكن تعديل هذا النص من لوحة التحكم.',
                        'description_en' => 'Demo description for the '.strtolower($item['en']).'. Edit this text from the admin panel.',
                        'slug_ar' => $slugs->unique(Product::class, 'slug_ar', $item['ar'], 'ar'),
                        'slug_en' => $slugs->unique(Product::class, 'slug_en', $item['en'], 'en'),
                        'sku' => 'SKU-'.str_pad((string) (($index + 1) * 100 + $position), 5, '0', STR_PAD_LEFT),
                        'is_active' => $item['published'] ?? true,
                        'is_featured' => $item['featured'] ?? false,
                        'sort_order' => $position * 10,
                    ]);

                    foreach (range(1, 2) as $imageIndex) {
                        ProductImage::query()->create([
                            'product_id' => $product->id,
                            'path' => $this->placeholder('products', $item['en'].' '.$imageIndex, 900, 900),
                            'alt_ar' => $item['ar'],
                            'alt_en' => $item['en'],
                            'sort_order' => ($imageIndex - 1) * 10,
                            'is_primary' => $imageIndex === 1,
                        ]);
                    }
                }
            });
        }
    }

    protected function seedSettings(): void
    {
        SiteSetting::query()->firstOrCreate([], [
            'site_name_ar' => 'متجر فايف',
            'site_name_en' => 'Five Store',
            'hero_title_ar' => 'كتالوج منتجات مصمَّم ليُقرأ بوضوح',
            'hero_title_en' => 'A product catalog built to be read clearly',
            'hero_subtitle_ar' => 'تصفّح الفئات والمنتجات بالعربية والإنكليزية، مع صور وتفاصيل كاملة لكل منتج.',
            'hero_subtitle_en' => 'Browse categories and products in Arabic and English, with images and full details for each item.',
            'contact_email' => 'hello@example.com',
            'contact_phone' => '+966 11 000 0000',
            'contact_address_ar' => 'الرياض، المملكة العربية السعودية',
            'contact_address_en' => 'Riyadh, Saudi Arabia',
            'footer_text_ar' => 'كتالوج عرض للمنتجات. لا تُجرى عمليات بيع عبر هذا الموقع.',
            'footer_text_en' => 'A product showcase. No sales are made through this site.',
            'meta_description_ar' => 'كتالوج منتجات ثنائي اللغة يعرض الفئات والمنتجات بالصور والتفاصيل.',
            'meta_description_en' => 'A bilingual product catalog presenting categories and products with images and details.',
        ]);
    }

    /**
     * Writes a small placeholder image so the seeded catalog renders with real
     * files rather than broken images. Returns null when GD is unavailable.
     */
    protected function placeholder(string $bucket, string $seed, int $width, int $height): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $directory = config("catalog.images.directories.{$bucket}");
        $path = $directory.'/seed-'.Str::slug($seed).'-'.substr(md5($seed), 0, 6).'.jpg';
        $disk = Storage::disk(config('catalog.images.disk'));

        if ($disk->exists($path)) {
            return $path;
        }

        $image = imagecreatetruecolor($width, $height);
        $hue = crc32($seed) % 360;

        for ($y = 0; $y < $height; $y++) {
            [$r, $g, $b] = $this->hsvToRgb($hue, 0.18, 0.95 - ($y / $height) * 0.25);
            $color = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $y, $width, $y, $color);
        }

        $ink = imagecolorallocate($image, 90, 100, 110);
        imagestring($image, 5, 20, 20, substr($seed, 0, 40), $ink);

        ob_start();
        imagejpeg($image, null, 82);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        $disk->put($path, $contents);

        return $path;
    }

    /**
     * @return array{int, int, int}
     */
    protected function hsvToRgb(float $h, float $s, float $v): array
    {
        $c = $v * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $v - $c;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return [
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255),
        ];
    }
}
