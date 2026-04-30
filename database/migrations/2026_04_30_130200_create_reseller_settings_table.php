<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Branding (override SiteSetting global untuk storefront per-reseller, future)
            $table->string('store_name')->nullable();
            $table->string('tagline')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('brand_color', 16)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('wa_number', 32)->nullable();

            // Pakasir per-reseller (encrypted di model)
            $table->string('pakasir_project')->nullable();
            $table->text('pakasir_api_key')->nullable();
            $table->boolean('pakasir_qris_only')->default(false);
            $table->unsignedSmallInteger('pakasir_order_expiry_minutes')->default(60);

            // Telegram bot per-reseller
            $table->text('tg_bot_token')->nullable();
            $table->string('tg_bot_username', 64)->nullable();
            $table->string('tg_webhook_secret', 64)->nullable();
            $table->text('tg_notif_bot_token')->nullable();
            $table->string('tg_admin_chat_id', 64)->nullable();

            // Fonnte per-reseller
            $table->text('fonnte_api_key')->nullable();
            $table->string('fonnte_admin_number', 32)->nullable();
            $table->boolean('fonnte_auto_send_credentials')->default(false);

            // Activation status (admin approve)
            $table->boolean('is_active')->default(false);
            $table->timestamp('activated_at')->nullable();

            $table->timestamps();

            $table->unique('tg_webhook_secret', 'reseller_settings_tg_webhook_secret_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_settings');
    }
};
