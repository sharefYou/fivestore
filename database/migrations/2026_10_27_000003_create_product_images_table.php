<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('path');
            $table->string('alt_ar', 180)->nullable();
            $table->string('alt_en', 180)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);

            // NULL for non-primary rows, 1 for the primary one: the unique index below
            // then allows many secondary images but only one primary image per product,
            // because SQL unique indexes do not compare NULLs with each other.
            $table->tinyInteger('primary_flag')
                ->virtualAs('CASE WHEN is_primary = 1 THEN 1 ELSE NULL END')
                ->nullable();

            $table->timestamps();

            $table->unique(['product_id', 'primary_flag'], 'product_images_single_primary_unique');
            $table->index(['product_id', 'sort_order'], 'product_images_product_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
