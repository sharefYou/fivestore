<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Builds the per-locale slugs used in public URLs.
 */
class SlugGenerator
{
    /**
     * Turn a title into a slug.
     *
     * Str::slug() transliterates to ASCII, which erases Arabic entirely, so
     * Arabic titles get a Unicode-preserving slug instead and only the
     * characters that are unsafe in a URL segment are replaced.
     */
    public function slugify(string $value, string $locale): string
    {
        $value = trim($value);

        if ($locale === 'ar') {
            $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
            $slug = trim($slug, '-');
            $slug = mb_strtolower($slug);
        } else {
            $slug = Str::slug($value);
        }

        // Titles made only of punctuation or emoji would slug to nothing.
        return $slug !== '' ? $slug : Str::lower(Str::random(8));
    }

    /**
     * A slug that no other row of this model holds, suffixing -2, -3 ... on
     * collision.
     *
     * Soft-deleted rows are included on purpose: the unique index covers them
     * too, so reusing a trashed row's slug would fail at the database and
     * would also break the URL if that row is ever restored.
     *
     * @param  class-string<Model>  $model
     */
    public function unique(string $model, string $column, string $value, string $locale, ?int $ignoreId = null): string
    {
        $base = $this->slugify($value, $locale);
        $candidate = $base;
        $suffix = 1;

        while ($this->taken($model, $column, $candidate, $ignoreId)) {
            $suffix++;
            $candidate = "{$base}-{$suffix}";
        }

        return $candidate;
    }

    /**
     * @param  class-string<Model>  $model
     */
    protected function taken(string $model, string $column, string $value, ?int $ignoreId): bool
    {
        $query = $model::query()->where($column, $value);

        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            /** @phpstan-ignore-next-line withTrashed() exists on soft-deleting models. */
            $query->withTrashed();
        }

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
