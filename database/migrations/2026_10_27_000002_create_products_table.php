<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Deleting a category that still holds products is refused by the database.
            $table->foreignId('category_id')->constrained()->restrictOnDelete();

            $table->string('name_ar', 160);
            $table->string('name_en', 160);
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();

            $table->string('slug_ar', 180)->unique();
            $table->string('slug_en', 180)->unique();

            // Optional; NULL repeats freely under a unique index, a value must be unique.
            $table->string('sku', 64)->nullable()->unique();

            $table->boolean('is_active')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Category page: published products of one category in display order.
            $table->index(['category_id', 'is_active', 'sort_order'], 'products_category_active_sort_index');
            // Home page: featured published products.
            $table->index(['is_featured', 'is_active'], 'products_featured_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
