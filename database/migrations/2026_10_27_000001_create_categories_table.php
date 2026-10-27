<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            $table->string('name_ar', 160);
            $table->string('name_en', 160);
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();

            // Slugs are unique per language so each locale keeps readable URLs.
            $table->string('slug_ar', 180)->unique();
            $table->string('slug_en', 180)->unique();

            $table->string('image_path')->nullable();

            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Storefront listing: active categories ordered for display.
            $table->index(['is_active', 'sort_order'], 'categories_active_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
