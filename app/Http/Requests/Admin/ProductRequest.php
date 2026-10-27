<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Support\ProductRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Server-side rules for writing a product, including its price.
 *
 * There is no public route that writes products; authorization defers to
 * ProductPolicy, so a non-administrator is rejected before any rule runs.
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? $this->user()?->can('update', $product) ?? false
            : $this->user()?->can('create', Product::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $ignore = $product instanceof Product ? $product->getKey() : null;

        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],

            'name_ar' => ['required', 'string', 'max:160'],
            'name_en' => ['required', 'string', 'max:160'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'description_en' => ['nullable', 'string', 'max:5000'],

            'slug_ar' => $this->slugRules('slug_ar', $ignore),
            'slug_en' => $this->slugRules('slug_en', $ignore),

            'sku' => [
                'nullable', 'string', 'max:64',
                Rule::unique('products', 'sku')->ignore($ignore),
            ],

            'price' => ProductRules::price(),

            // Publishing flags are administrative state. They are listed here
            // so they are validated when present, never taken from public
            // input: no public route reaches this request.
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function slugRules(string $column, ?int $ignore): array
    {
        return [
            'required', 'string', 'max:180',
            'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u',
            Rule::unique('products', $column)->ignore($ignore),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug_ar.regex' => __('admin.validation.slug'),
            'slug_en.regex' => __('admin.validation.slug'),
        ];
    }
}
