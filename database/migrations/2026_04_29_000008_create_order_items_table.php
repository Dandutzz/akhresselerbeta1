<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('stock_id')->nullable()->constrained('stocks')->nullOnDelete();
            $table->unsignedSmallInteger('qty')->default(1);
            $table->unsignedInteger('unit_price')->default(0);
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
