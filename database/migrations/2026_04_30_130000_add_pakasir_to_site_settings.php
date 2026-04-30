<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            // Slug project Pakasir (mis. `akhpremium`).
            $table->string('pakasir_project')->nullable()->after('fonnte_webhook_secret');
            // API key Pakasir — disimpan terenkripsi (encrypted cast).
            $table->text('pakasir_api_key')->nullable()->after('pakasir_project');
            // Batasi metode pembayaran ke QRIS saja.
            $table->boolean('pakasir_qris_only')->default(false)->after('pakasir_api_key');
            // Default masa berlaku order (menit) sebelum auto-expire.
            $table->unsignedInteger('pakasir_order_expiry_minutes')->default(60)->after('pakasir_qris_only');
            // Base URL Pakasir (default https://app.pakasir.com). Kosongkan utk pakai default.
            $table->string('pakasir_base_url')->nullable()->after('pakasir_order_expiry_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'pakasir_project',
                'pakasir_api_key',
                'pakasir_qris_only',
                'pakasir_order_expiry_minutes',
                'pakasir_base_url',
            ]);
        });
    }
};
