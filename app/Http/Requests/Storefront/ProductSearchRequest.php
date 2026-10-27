<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the public search box. An over-long or malformed term is a bad
 * request, not a 500 and not an expensive query.
 */
class ProductSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:180'],
            // `page` is deliberately not validated here: App\Support\Pagination
            // answers a malformed or out-of-range page with a 404, which is
            // the right response for an address that does not exist, rather
            // than a redirect carrying validation errors.
        ];
    }

    public function term(): ?string
    {
        $term = trim((string) $this->query('q', ''));

        return $term === '' ? null : $term;
    }

    public function categorySlug(): ?string
    {
        $slug = trim((string) $this->query('category', ''));

        return $slug === '' ? null : $slug;
    }
}
