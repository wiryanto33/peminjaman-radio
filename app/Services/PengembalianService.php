<?php

namespace App\Services;

use App\Models\Peminjaman;
use App\Models\Pengembalian;
use App\Models\Radio;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PengembalianService
{
    /**
     * Proses pengembalian radio.
     *
     * Logika: 1 Radio = 1 unit fisik dengan serial_no unik.
     * - Dikembalikan BAIK       → status = tersedia, stok = 1, kondisi = baik
     * - Dikembalikan RUSAK      → status = perbaikan, stok = 0, kondisi = rusak_ringan/berat
     *
     * Unit lain (serial berbeda) TIDAK terpengaruh sama sekali.
     */
    public function processReturn(Pengembalian $pengembalian): void
    {
        DB::transaction(function () use ($pengembalian) {
            $radio      = Radio::whereKey($pengembalian->radio_id)->lockForUpdate()->firstOrFail();
            $peminjaman = Peminjaman::whereKey($pengembalian->peminjaman_id)->lockForUpdate()->firstOrFail();

            // Validasi status peminjaman
            if (!in_array($peminjaman->status, [
                Peminjaman::STATUS_DIPINJAM,
                Peminjaman::STATUS_APPROVED,
                Peminjaman::STATUS_TERLAMBAT,
            ], true)) {
                throw ValidationException::withMessages([
                    'peminjaman_id' => 'Status peminjaman tidak valid untuk pengembalian.',
                ]);
            }

            // Tandai peminjaman sebagai dikembalikan
            $peminjaman->status = Peminjaman::STATUS_DIKEMBALIKAN;
            $peminjaman->save();

            // Update status radio berdasarkan kondisi saat dikembalikan
            match ($pengembalian->kondisi_kembali) {
                // Kondisi BAIK → unit kembali tersedia
                Pengembalian::KONDISI_BAIK => (function () use ($radio) {
                    $radio->status = Radio::STATUS_TERSEDIA;
                    $radio->stok   = 1; // 1 unit fisik kembali tersedia
                    $radio->kondisi = Radio::KONDISI_BAIK;
                })(),

                // Kondisi RUSAK RINGAN → unit masuk perbaikan
                Pengembalian::KONDISI_RUSAK_RINGAN => (function () use ($radio) {
                    $radio->status  = Radio::STATUS_PERBAIKAN;
                    $radio->stok    = 0; // tidak bisa dipinjam selama perbaikan
                    $radio->kondisi = Radio::KONDISI_RUSAK_RINGAN;
                })(),

                // Kondisi RUSAK BERAT → unit masuk perbaikan (kondisi berat)
                Pengembalian::KONDISI_RUSAK_BERAT => (function () use ($radio) {
                    $radio->status  = Radio::STATUS_PERBAIKAN;
                    $radio->stok    = 0; // tidak bisa dipinjam selama perbaikan
                    $radio->kondisi = Radio::KONDISI_RUSAK_BERAT;
                })(),

                default => (function () use ($radio) {
                    $radio->status = Radio::STATUS_TERSEDIA;
                    $radio->stok   = 1;
                    $radio->kondisi = Radio::KONDISI_BAIK;
                })(),
            };

            $radio->save();
        });
    }
}
