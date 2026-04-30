<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            // Custom SEO — di-render di <head> layouts/app.blade.php.
            // Semua field opsional; fallback ke nilai default.
            $table->string('seo_meta_title')->nullable();
            $table->text('seo_meta_description')->nullable();
            $table->string('seo_meta_keywords', 1000)->nullable();
            $table->string('seo_og_image_path')->nullable();
            $table->string('seo_canonical_url', 500)->nullable();
            // Contoh: 'index,follow' atau 'noindex,nofollow'.
            $table->string('seo_robots', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'seo_meta_title',
                'seo_meta_description',
                'seo_meta_keywords',
                'seo_og_image_path',
                'seo_canonical_url',
                'seo_robots',
            ]);
        });
    }
};
