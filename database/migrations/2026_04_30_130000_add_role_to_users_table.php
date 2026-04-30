<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                // Pakai string (bukan enum) supaya kompatibel sqlite + mudah extend.
                $table->string('role', 16)->default('customer')->after('is_admin');
            }
            if (! Schema::hasColumn('users', 'reseller_slug')) {
                $table->string('reseller_slug', 64)->nullable()->after('role');
            }
            if (! Schema::hasColumn('users', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('reseller_slug');
            }
        });

        if (Schema::hasColumn('users', 'reseller_slug')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('reseller_slug', 'users_reseller_slug_unique');
            });
        }

        // Backfill: derive role dari is_admin lama.
        DB::table('users')->where('is_admin', true)->update(['role' => 'admin']);
        DB::table('users')->where('is_admin', false)->update(['role' => 'customer']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'reseller_slug')) {
                try {
                    $table->dropUnique('users_reseller_slug_unique');
                } catch (Throwable $e) {
                    // index already gone (sqlite) — ignore
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['approved_at', 'reseller_slug', 'role'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
