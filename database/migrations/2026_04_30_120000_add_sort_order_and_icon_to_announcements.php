<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('id')->index();
            $table->string('icon', 16)->nullable()->after('image');
        });
        DB::statement('UPDATE announcements SET sort_order = id WHERE sort_order = 0');
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['sort_order', 'icon']);
        });
    }
};
