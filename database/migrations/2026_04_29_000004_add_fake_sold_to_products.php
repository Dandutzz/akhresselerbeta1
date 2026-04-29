<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Offset jumlah terjual palsu (untuk social proof). Ditambahkan ke sold_count
            // saat ditampilkan ke publik. Admin sebaiknya hanya menaikkan, tidak menurunkan.
            $table->unsignedInteger('fake_sold_count')->default(0)->after('sold_count');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('fake_sold_count');
        });
    }
};
