<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\ImageStorage;

class CategoryObserver
{
    public function __construct(protected ImageStorage $images) {}

    /**
     * Replacing the image leaves the previous file behind unless we remove it.
     */
    public function updated(Category $category): void
    {
        if ($category->wasChanged('image_path')) {
            $this->images->delete($category->getOriginal('image_path'));
        }
    }

    /**
     * A soft-deleted category can still be restored, so its file is kept until
     * the record is deleted for good.
     */
    public function forceDeleted(Category $category): void
    {
        $this->images->delete($category->image_path);
    }
}
