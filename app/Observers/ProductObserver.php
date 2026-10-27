<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\ImageStorage;

class ProductObserver
{
    public function __construct(protected ImageStorage $images) {}

    /**
     * The foreign key cascades the image *rows* away, but the database knows
     * nothing about the files, so they are removed here before the delete.
     */
    public function forceDeleting(Product $product): void
    {
        $product->images()->get()->each(
            fn ($image) => $this->images->delete($image->path)
        );
    }
}
