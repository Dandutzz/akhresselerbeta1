<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            // Garansi durasi (hari). 0 / null = tanpa garansi.
            $table->unsignedSmallInteger('warranty_days')->nullable()->after('is_auto_send');
            // sharing | private | sharing_antilimit | null
            $table->string('share_type', 32)->nullable()->after('warranty_days');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['warranty_days', 'share_type']);
        });
    }
};
