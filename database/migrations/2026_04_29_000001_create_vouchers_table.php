<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name')->nullable();
            // type: percent | fixed
            $table->string('type', 12)->default('percent');
            $table->unsignedInteger('value')->default(0); // percent 0-100 OR rupiah
            $table->unsignedInteger('min_purchase')->default(0);
            $table->unsignedInteger('max_discount')->default(0); // 0 = no cap
            $table->unsignedInteger('usage_limit')->default(0); // 0 = unlimited
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
