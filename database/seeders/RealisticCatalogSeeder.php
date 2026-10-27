<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageStorage;
use App\Services\RemoteImageFetcher;
use App\Services\SlugGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Builds the demonstration catalog: eight published categories of forty
 * products each, with prices, bilingual copy and real photographs.
 *
 * Safe to re-run. The previous catalog is force-deleted first so the model
 * observers remove the image files with it; without that every run would
 * leave another 640 orphaned files on disk.
 *
 * Site settings are deliberately untouched: the contact details there belong
 * to the shop owner, not to this seeder.
 */
class RealisticCatalogSeeder extends Seeder
{
    /** Photographs are fetched from this one host, and nowhere else. */
    protected const IMAGE_QUERY = '?w=1000&q=75&fm=jpg&fit=crop';

    /** Distinct photographs actually pulled over the network. */
    protected int $fetched = 0;

    /** Image files written from an already-downloaded photograph. */
    protected int $stored = 0;

    protected int $placeholders = 0;

    /** @var array<string, true> */
    protected array $seenUrls = [];

    public function run(SlugGenerator $slugs, RemoteImageFetcher $fetcher, ImageStorage $images): void
    {
        $this->guardAgainstUnsafeRun();
        $this->forgetPreviousCatalog();

        $files = [
            'home-furniture',
            'lighting',
            'kitchenware',
            'decor-accessories',
            'textiles-bedding',
            'bathroom',
            'storage-organization',
            'outdoor-balcony',
        ];

        // One product in the whole catalog stays a draft, so the publishing
        // rules remain visible on a running site.
        $draftAssigned = false;

        foreach ($files as $index => $file) {
            $data = require database_path("seeders/data/{$file}.php");

            $this->command?->info("Seeding {$data['en']} ...");

            $category = $this->createCategory($data, $index, $slugs, $fetcher, $images);

            foreach (array_values($data['products']) as $position => $item) {
                $isDraft = ! $draftAssigned && $index === 0 && $position === 4;
                $draftAssigned = $draftAssigned || $isDraft;

                $this->createProduct(
                    $category,
                    $data,
                    $item,
                    $position,
                    isFeatured: $position < 2,
                    isPublished: ! $isDraft,
                    slugs: $slugs,
                    fetcher: $fetcher,
                    images: $images,
                );
            }
        }

        $fetcher->flushCache();

        $this->command?->newLine();
        $this->command?->info(sprintf(
            'Catalog seeded: %d categories, %d products, %d product images.',
            Category::query()->count(),
            Product::query()->count(),
            ProductImage::query()->count(),
        ));
        $this->command?->info(sprintf(
            'Images: %d files written (%d category + %d product), from %d photographs fetched over the network, %d generated placeholders.',
            $this->stored + $this->placeholders,
            Category::query()->whereNotNull('image_path')->count(),
            ProductImage::query()->count(),
            $this->fetched,
            $this->placeholders,
        ));
    }

    /**
     * This seeder deletes the entire catalog before rebuilding it. That is
     * correct for a demonstration database and wrong everywhere else, so it
     * refuses to run where the loss would matter.
     */
    protected function guardAgainstUnsafeRun(): void
    {
        $allowed = filter_var(env('CATALOG_SEEDER_ALLOW_DESTRUCTIVE', false), FILTER_VALIDATE_BOOL);

        if (app()->isProduction() && ! $allowed) {
            throw new RuntimeException(
                'RealisticCatalogSeeder deletes every category and product before seeding, '.
                'and will not run in production. If that is genuinely intended, take a backup '.
                'first and set CATALOG_SEEDER_ALLOW_DESTRUCTIVE=true.'
            );
        }

        $existingProducts = Product::withTrashed()->count();
        $existingCategories = Category::withTrashed()->count();

        if ($existingProducts === 0 && $existingCategories === 0) {
            return;
        }

        $this->command?->warn(sprintf(
            'This will permanently delete %d categories and %d products (including soft-deleted rows) and their image files.',
            $existingCategories,
            $existingProducts,
        ));

        // `--force` is the existing, well-understood way to say "yes, I mean
        // it" to a seeding command, so it doubles as the acknowledgement
        // here. Without it the question is asked, and a non-interactive
        // console answers with the safe default and cancels.
        if ($this->command === null || $this->command->option('force')) {
            return;
        }

        if (! $this->command->confirm('Delete the existing catalog and reseed?', false)) {
            throw new RuntimeException('Seeding cancelled; nothing was deleted.');
        }
    }

    /**
     * Remove the previous catalog through Eloquent so the observers delete the
     * image files too. Products go first: the foreign key refuses to drop a
     * category that still has any.
     */
    protected function forgetPreviousCatalog(): void
    {
        Product::withTrashed()->with('images')->chunkById(50, function ($products): void {
            $products->each->forceDelete();
        });

        Category::withTrashed()->chunkById(50, function ($categories): void {
            $categories->each->forceDelete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createCategory(
        array $data,
        int $index,
        SlugGenerator $slugs,
        RemoteImageFetcher $fetcher,
        ImageStorage $images,
    ): Category {
        return DB::transaction(fn (): Category => Category::query()->create([
            'name_ar' => $data['ar'],
            'name_en' => $data['en'],
            'description_ar' => $data['description_ar'],
            'description_en' => $data['description_en'],
            'slug_ar' => $slugs->unique(Category::class, 'slug_ar', $data['ar'], 'ar'),
            'slug_en' => $slugs->unique(Category::class, 'slug_en', $data['en'], 'en'),
            'image_path' => $this->imageFor($data['images'][0], 'categories', $data['en'], $fetcher, $images),
            'is_active' => true,
            'sort_order' => $index * 10,
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $item
     */
    protected function createProduct(
        Category $category,
        array $data,
        array $item,
        int $position,
        bool $isFeatured,
        bool $isPublished,
        SlugGenerator $slugs,
        RemoteImageFetcher $fetcher,
        ImageStorage $images,
    ): void {
        DB::transaction(function () use ($category, $data, $item, $position, $isFeatured, $isPublished, $slugs, $fetcher, $images): void {
            $product = Product::query()->create([
                'category_id' => $category->id,
                'name_ar' => $item['ar'],
                'name_en' => $item['en'],
                'description_ar' => $item['description_ar'],
                'description_en' => $item['description_en'],
                'slug_ar' => $slugs->unique(Product::class, 'slug_ar', $item['ar'], 'ar'),
                'slug_en' => $slugs->unique(Product::class, 'slug_en', $item['en'], 'en'),
                'sku' => $data['sku_prefix'].'-'.($data['sku_start'] + $position),
                'price' => $item['price'],
                'is_active' => $isPublished,
                'is_featured' => $isFeatured,
                'sort_order' => $position * 10,
            ]);

            // Two photographs each, taken from this category's pool so the
            // pictures suit the shelf they sit on.
            $pool = $data['images'];

            foreach ([0, 1] as $slot) {
                $source = $pool[($position + $slot) % count($pool)];

                $path = $this->imageFor($source, 'products', $item['en'].' '.($slot + 1), $fetcher, $images);

                if ($path === null) {
                    continue;
                }

                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'path' => $path,
                    'alt_ar' => $item['ar'],
                    'alt_en' => $item['en'],
                    'sort_order' => $slot * 10,
                    'is_primary' => $slot === 0,
                ]);
            }
        });
    }

    /**
     * Fetch a photograph and store it locally, falling back to a generated
     * placeholder so one failed download never stops the seeder.
     *
     * @param  'categories'|'products'  $bucket
     */
    protected function imageFor(
        string $photoId,
        string $bucket,
        string $seed,
        RemoteImageFetcher $fetcher,
        ImageStorage $images,
    ): ?string {
        $url = 'https://'.RemoteImageFetcher::ALLOWED_HOST.'/'.$photoId.self::IMAGE_QUERY;

        try {
            // The fetcher downloads a given URL once and reuses the buffered
            // copy, so count the network trip separately from the files
            // written: 648 images here come from far fewer photographs.
            $isNewUrl = ! isset($this->seenUrls[$url]);

            $temporary = $fetcher->download($url);
            $path = $images->storeFromPath($temporary, $bucket);

            if ($isNewUrl) {
                $this->seenUrls[$url] = true;
                $this->fetched++;
            }

            $this->stored++;

            return $path;
        } catch (\Throwable $e) {
            $this->command?->warn("  image fallback for {$seed}: {$e->getMessage()}");
        }

        $path = $this->placeholder($bucket, $seed);

        if ($path !== null) {
            $this->placeholders++;
        }

        return $path;
    }

    /**
     * A small generated image, used only when a download or its validation
     * failed.
     */
    protected function placeholder(string $bucket, string $seed): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $directory = config("catalog.images.directories.{$bucket}");
        $disk = Storage::disk(config('catalog.images.disk'));
        $path = $directory.'/'.Str::uuid()->toString().'.jpg';

        [$width, $height] = $bucket === 'categories' ? [1200, 675] : [900, 900];

        $image = imagecreatetruecolor($width, $height);
        $hue = crc32($seed) % 360;

        for ($y = 0; $y < $height; $y++) {
            [$r, $g, $b] = $this->hsvToRgb($hue, 0.18, 0.95 - ($y / $height) * 0.25);
            imageline($image, 0, $y, $width, $y, imagecolorallocate($image, $r, $g, $b));
        }

        imagestring($image, 5, 20, 20, substr($seed, 0, 40), imagecolorallocate($image, 90, 100, 110));

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
