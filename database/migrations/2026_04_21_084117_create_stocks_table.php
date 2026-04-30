<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrasi ini sebelumnya bertimestamp 2026_04_21_082001 — sebelum
        // `product_variants` (084116). MySQL/MariaDB menolak FK ke tabel yang
        // belum ada, jadi urutan dipindah ke 084117. Untuk instalasi lama
        // (mis. SQLite) yang sudah punya tabel `stocks` di-create dengan
        // timestamp lama, skip biar tidak error "table already exists".
        if (Schema::hasTable('stocks')) {
            return;
        }

        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();

            // Kolom detail stok
            $table->string('email_or_phone');
            $table->string('password');
            $table->text('additional_info')->nullable(); // Untuk keterangan tambahan

            $table->boolean('is_sold')->default(false);
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
