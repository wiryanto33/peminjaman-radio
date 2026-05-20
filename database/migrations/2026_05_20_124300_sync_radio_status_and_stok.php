<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sinkronkan status dan stok radio berdasarkan logika baru:
     * 1 Radio = 1 unit fisik (serial_no unik)
     * - stok harus selalu 0 atau 1
     * - status mencerminkan kondisi unit secara langsung
     */
    public function up(): void
    {
        // 1. Normalkan stok_total ke 1 untuk semua radio (karena tiap record = 1 unit fisik)
        DB::statement("UPDATE radios SET stok_total = 1");

        // 2. Radio yang sedang DIPINJAM (ada peminjaman aktif) → status=dipinjam, stok=0
        DB::statement("
            UPDATE radios
            SET status = 'dipinjam', stok = 0
            WHERE id IN (
                SELECT DISTINCT radio_id FROM peminjaman
                WHERE status IN ('dipinjam', 'approved', 'terlambat')
            )
        ");

        // 3. Radio yang status PERBAIKAN → stok=0 (tidak bisa dipinjam)
        DB::statement("
            UPDATE radios
            SET stok = 0
            WHERE status = 'perbaikan'
        ");

        // 4. Radio yang TERSEDIA → stok=1
        DB::statement("
            UPDATE radios
            SET stok = 1
            WHERE status = 'tersedia'
        ");

        // 5. Perbaiki radio yang tidak punya peminjaman aktif tapi statusnya masih 'dipinjam'
        //    → kembalikan ke tersedia
        DB::statement("
            UPDATE radios
            SET status = 'tersedia', stok = 1
            WHERE status = 'dipinjam'
              AND id NOT IN (
                SELECT DISTINCT radio_id FROM peminjaman
                WHERE status IN ('dipinjam', 'approved', 'terlambat')
              )
        ");
    }

    public function down(): void
    {
        // Tidak ada rollback spesifik (data akan dihitung ulang)
    }
};
