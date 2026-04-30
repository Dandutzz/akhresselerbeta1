<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Asal order: 'web' (checkout via situs / cart) atau 'telegram'
            // (checkout via Telegram bot). Dipakai admin untuk membedakan
            // saluran penjualan di /admin/orders.
            $table->string('source', 16)->default('web')->index();

            // Cache QRIS payload (string EMVCo) yang dikembalikan Pakasir.
            // Disimpan agar invoice & Telegram bot dapat me-render gambar QR
            // tanpa hit API Pakasir berulang kali.
            $table->text('payment_qr_string')->nullable();

            // Metode pembayaran yang DI-REQUEST saat membuat transaksi via API
            // (bukan yang TERPILIH oleh user pada flow URL). Contoh: 'qris'.
            $table->string('payment_method_requested', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['source', 'payment_qr_string', 'payment_method_requested']);
        });
    }
};
