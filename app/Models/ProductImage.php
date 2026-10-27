<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedAttributes;
use App\Observers\ProductImageObserver;
use Database\Factories\ProductImageFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property string $path
 * @property string|null $alt_ar
 * @property string|null $alt_en
 * @property int $sort_order
 * @property bool $is_primary
 */
#[ObservedBy(ProductImageObserver::class)]
class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use HasFactory;

    use HasTranslatedAttributes;

    protected $fillable = [
        'path',
        'alt_ar',
        'alt_en',
        'sort_order',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @param  Builder<ProductImage>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id');
    }
}
