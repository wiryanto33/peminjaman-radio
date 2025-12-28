<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('radios', function (Blueprint $table) {
            if (!Schema::hasColumn('radios', 'stok')) {
                $table->unsignedInteger('stok')->default(1)->after('kondisi');
            }
            if (!Schema::hasColumn('radios', 'stok_total')) {
                $table->unsignedInteger('stok_total')->default(1)->after('stok');
            }
        });

        // Initialize stok_total to be equal to stok for existing rows
        try {
            DB::statement('UPDATE `radios` SET `stok_total` = COALESCE(`stok`, 1) WHERE `stok_total` IS NULL OR `stok_total` = 0');
        } catch (\Throwable $e) {
            // ignore
        }

        // Expand enum for status to include stok_habis if database supports enum
        try {
            DB::statement("ALTER TABLE `radios` MODIFY `status` ENUM('tersedia','dipinjam','perbaikan','stok_habis') NOT NULL DEFAULT 'tersedia'");
        } catch (\Throwable $e) {
            // Fallback silently if not MySQL/enum or already adjusted
        }

        Schema::table('peminjaman', function (Blueprint $table) {
            if (!Schema::hasColumn('peminjaman', 'jumlah')) {
                $table->unsignedInteger('jumlah')->default(1)->after('radio_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('peminjaman', function (Blueprint $table) {
            if (Schema::hasColumn('peminjaman', 'jumlah')) {
                $table->dropColumn('jumlah');
            }
        });

        // Try to revert enum change (remove stok_habis)
        try {
            DB::statement("ALTER TABLE `radios` MODIFY `status` ENUM('tersedia','dipinjam','perbaikan') NOT NULL DEFAULT 'tersedia'");
        } catch (\Throwable $e) {
            // ignore
        }

        Schema::table('radios', function (Blueprint $table) {
            if (Schema::hasColumn('radios', 'stok')) {
                $table->dropColumn('stok');
            }
            if (Schema::hasColumn('radios', 'stok_total')) {
                $table->dropColumn('stok_total');
            }
        });
    }
};
