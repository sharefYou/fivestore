<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * Every administrative action on a category is gated here, so the server
 * decides what is allowed no matter which button the browser rendered.
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, Category $category): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Category $category): bool
    {
        return $user->is_admin;
    }

    /**
     * A category that still holds products cannot be deleted; its products
     * must be moved or removed first.
     *
     * Trashed products count, because the foreign key counts them too: the
     * rows are still there and the database would refuse the delete.
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->is_admin && ! $category->products()->withTrashed()->exists();
    }

    public function restore(User $user, Category $category): bool
    {
        return $user->is_admin;
    }

    public function forceDelete(User $user, Category $category): bool
    {
        return $this->delete($user, $category);
    }
}
