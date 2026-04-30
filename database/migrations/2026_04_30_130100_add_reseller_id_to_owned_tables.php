<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daftar tabel yang resource-nya dimiliki oleh seorang reseller.
     * Semua kolom `reseller_id` nullable supaya migrasi tidak gagal pada
     * data lama. Backfill di-isi ke admin pertama agar ownership eksplisit.
     */
    private array $tables = [
        'products',
        'product_variants',
        'stocks',
        'orders',
        'vouchers',
        'categories',
        'articles',
        'announcements',
        'faqs',
        'flashsales',
        'testimonials',
        'cart_items',
        'order_items',
        'reviews',
        'audit_logs',
        'wallet_transactions',
        'telegram_link_tokens',
        'telegram_bot_states',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            if (Schema::hasColumn($tableName, 'reseller_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('reseller_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index('reseller_id');
            });
        }

        // Backfill: assign semua data lama ke admin pertama (owner default).
        $firstAdminId = DB::table('users')->where('role', 'admin')->orderBy('id')->value('id');

        if ($firstAdminId) {
            foreach ($this->tables as $tableName) {
                if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'reseller_id')) {
                    DB::table($tableName)->whereNull('reseller_id')->update(['reseller_id' => $firstAdminId]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'reseller_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                try {
                    $table->dropForeign([$tableName.'_reseller_id_foreign']);
                } catch (Throwable $e) {
                    // sqlite or already dropped
                }
                try {
                    $table->dropIndex([$tableName.'_reseller_id_index']);
                } catch (Throwable $e) {
                    // ignore
                }
                $table->dropColumn('reseller_id');
            });
        }
    }
};
