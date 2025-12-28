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
     * Saat pengembalian dibuat: set status peminjaman→RETURNED
     * dan status radio berdasarkan kondisi_kembali.
     */
    public function processReturn(Pengembalian $pengembalian): void
    {
        DB::transaction(function () use ($pengembalian) {
            // Lock radio & peminjaman biar konsisten
            $radio = Radio::whereKey($pengembalian->radio_id)->lockForUpdate()->firstOrFail();
            $peminjaman = Peminjaman::whereKey($pengembalian->peminjaman_id)->lockForUpdate()->firstOrFail();

            if (!in_array($peminjaman->status, [
                Peminjaman::STATUS_DIPINJAM,
                Peminjaman::STATUS_APPROVED,
                Peminjaman::STATUS_TERLAMBAT
            ], true)) {
                throw ValidationException::withMessages([
                    'peminjaman_id' => 'Status peminjaman tidak valid untuk pengembalian.',
                ]);
            }

            // Update status peminjaman
            $peminjaman->status = Peminjaman::STATUS_DIKEMBALIKAN;
            $peminjaman->save();

            // Tentukan status radio dari kondisi kembali
            $statusRadio = match ($pengembalian->kondisi_kembali) {
                Pengembalian::KONDISI_BAIK        => Radio::STATUS_TERSEDIA,
                Pengembalian::KONDISI_RUSAK_RINGAN,
                Pengembalian::KONDISI_RUSAK_BERAT => Radio::STATUS_PERBAIKAN,
                default => Radio::STATUS_TERSEDIA,
            };

            // Kelola stok: jika kembali dengan kondisi baik, tambahkan stok
            if ($statusRadio === Radio::STATUS_TERSEDIA) {
                $qty = max(1, (int) optional($peminjaman)->jumlah);
                $radio->stok = (int) $radio->stok + $qty;
                // Set status: stok habis / tersedia
                $radio->status = (int) $radio->stok === 0 ? Radio::STATUS_STOK_HABIS : Radio::STATUS_TERSEDIA;
            } else {
                // Jika perbaikan, tetap tandai perbaikan (meskipun stok mungkin > 0)
                $radio->status = Radio::STATUS_PERBAIKAN;
            }
            // Optional: update kondisi sesuai kondisi_kembali
            if ($statusRadio === Radio::STATUS_TERSEDIA) {
                $radio->kondisi = Radio::KONDISI_BAIK;
            } elseif (in_array($statusRadio, [Radio::STATUS_PERBAIKAN], true)) {
                $radio->kondisi = $pengembalian->kondisi_kembali === Pengembalian::KONDISI_RUSAK_BERAT
                    ? Radio::KONDISI_RUSAK_BERAT
                    : Radio::KONDISI_RUSAK_RINGAN;
            }
            $radio->save();
        });
    }
}
