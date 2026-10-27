<?php

namespace App\Observers;

use App\Models\ProductImage;
use App\Services\ImageStorage;

class ProductImageObserver
{
    public function __construct(protected ImageStorage $images) {}

    public function updated(ProductImage $image): void
    {
        if ($image->wasChanged('path')) {
            $this->images->delete($image->getOriginal('path'));
        }
    }

    /**
     * Product images are not soft-deleted, so the file goes with the row.
     */
    public function deleted(ProductImage $image): void
    {
        $this->images->delete($image->path);
    }
}
