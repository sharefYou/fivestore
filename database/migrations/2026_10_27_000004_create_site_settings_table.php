<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately a single-row table of known columns rather than a generic
        // key/value CMS: the editable surface is small and stays validatable.
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();

            $table->string('site_name_ar', 120);
            $table->string('site_name_en', 120);

            $table->string('hero_title_ar', 180)->nullable();
            $table->string('hero_title_en', 180)->nullable();
            $table->text('hero_subtitle_ar')->nullable();
            $table->text('hero_subtitle_en')->nullable();

            $table->string('logo_path')->nullable();

            $table->string('contact_email', 160)->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('contact_address_ar', 255)->nullable();
            $table->string('contact_address_en', 255)->nullable();

            $table->string('social_facebook')->nullable();
            $table->string('social_instagram')->nullable();
            $table->string('social_whatsapp')->nullable();

            $table->string('footer_text_ar', 255)->nullable();
            $table->string('footer_text_en', 255)->nullable();

            $table->string('meta_description_ar', 255)->nullable();
            $table->string('meta_description_en', 255)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
