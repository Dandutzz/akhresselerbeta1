<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            // Fake-sold counter (sosial proof)
            $table->boolean('fake_sold_enabled')->default(false)->after('fonnte_webhook_secret');

            // Floating notification (notif mengambang)
            $table->boolean('floating_notif_enabled')->default(false);
            $table->boolean('floating_notif_use_real')->default(true);
            $table->boolean('floating_notif_use_fake')->default(true);
            $table->unsignedSmallInteger('floating_notif_interval_min')->default(20);
            $table->unsignedSmallInteger('floating_notif_interval_max')->default(60);
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'fake_sold_enabled',
                'floating_notif_enabled',
                'floating_notif_use_real',
                'floating_notif_use_fake',
                'floating_notif_interval_min',
                'floating_notif_interval_max',
            ]);
        });
    }
};
