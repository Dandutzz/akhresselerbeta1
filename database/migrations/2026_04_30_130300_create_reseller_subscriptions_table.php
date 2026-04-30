<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Skeleton billing — belum di-enforce. Saat reseller telat bayar, suspend
     * gate akan ditambahkan di PR berikutnya. MVP hanya track status sewa flat.
     */
    public function up(): void
    {
        Schema::create('reseller_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 32)->default('flat');
            $table->unsignedInteger('monthly_fee')->default(250000); // Rp
            $table->date('current_period_start')->nullable();
            $table->date('current_period_end')->nullable();
            $table->string('status', 16)->default('trial'); // trial|active|past_due|suspended|cancelled
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_subscriptions');
    }
};
