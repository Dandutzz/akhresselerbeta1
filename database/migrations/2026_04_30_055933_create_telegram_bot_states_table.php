<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * State machine sederhana untuk multi-step bot flow
     * (mis. user lagi pilih varian → pilih qty → konfirmasi).
     */
    public function up(): void
    {
        Schema::create('telegram_bot_states', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id', 32)->unique();
            $table->string('state', 32)->nullable();      // idle, browsing, cart, awaiting_payment, etc.
            $table->json('payload')->nullable();          // data sementara (cart items, dll)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_bot_states');
    }
};
