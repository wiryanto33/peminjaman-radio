<?php

namespace App\Services;

use App\Models\Peminjaman;
use App\Models\Radio;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PeminjamanService
{
    /**
     * Approve peminjaman → tandai radio borrowed (opsional sekaligus serah-terima).
     */
    public function approve(Peminjaman $peminjaman, bool $setBorrowedNow = false): Peminjaman
    {
        return DB::transaction(function () use ($peminjaman, $setBorrowedNow) {
            // Lock radio supaya tidak double-booking
            $radio = Radio::whereKey($peminjaman->radio_id)->lockForUpdate()->firstOrFail();

            if ($peminjaman->status !== Peminjaman::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya peminjaman berstatus PENDING yang bisa di-approve.',
                ]);
            }

            // Validasi stok cukup bila langsung serah-terima
            if ($setBorrowedNow) {
                if ((int) $radio->stok < (int) ($peminjaman->jumlah ?? 1)) {
                    throw ValidationException::withMessages([
                        'radio_id' => 'Stok radio tidak mencukupi untuk dipinjam.',
                    ]);
                }
            }

            $peminjaman->status = $setBorrowedNow ? Peminjaman::STATUS_DIPINJAM : Peminjaman::STATUS_APPROVED;
            $peminjaman->save();

            // Jika langsung serah-terima: kurangi stok dan set status radio sesuai stok
            if ($setBorrowedNow) {
                $qty = max(1, (int) ($peminjaman->jumlah ?? 1));
                $radio->stok = max(0, (int) $radio->stok - $qty);
                $radio->status = $radio->stok === 0 ? Radio::STATUS_STOK_HABIS : Radio::STATUS_TERSEDIA;
                $radio->save();
            }

            return $peminjaman->fresh();
        });
    }

    /**
     * Tandai benar-benar diambil (serah-terima fisik).
     */
    public function markBorrowed(Peminjaman $peminjaman): Peminjaman
    {
        return DB::transaction(function () use ($peminjaman) {
            $radio = Radio::whereKey($peminjaman->radio_id)->lockForUpdate()->firstOrFail();

            if (!in_array($peminjaman->status, [Peminjaman::STATUS_PENDING, Peminjaman::STATUS_APPROVED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya PENDING/APPROVED yang bisa diubah menjadi BORROWED.',
                ]);
            }

            // Validasi stok cukup
            if ((int) $radio->stok < (int) ($peminjaman->jumlah ?? 1)) {
                throw ValidationException::withMessages([
                    'radio_id' => 'Stok radio tidak mencukupi untuk dipinjam.',
                ]);
            }

            $peminjaman->update(['status' => Peminjaman::STATUS_DIPINJAM]);
            $qty = max(1, (int) ($peminjaman->jumlah ?? 1));
            $newStok = max(0, (int) $radio->stok - $qty);
            $newStatus = $newStok === 0 ? Radio::STATUS_STOK_HABIS : Radio::STATUS_TERSEDIA;
            $radio->update([
                'stok' => $newStok,
                'status' => $newStatus,
            ]);

            return $peminjaman->fresh();
        });
    }

    /**
     * Batalkan peminjaman (sebelum borrowed). Radio tetap available.
     */
    public function cancel(Peminjaman $peminjaman): Peminjaman
    {
        return DB::transaction(function () use ($peminjaman) {
            if (in_array($peminjaman->status, [Peminjaman::STATUS_DIPINJAM, Peminjaman::STATUS_DIKEMBALIKAN], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Peminjaman yang sudah DIPINJAM/DIKEMBALIKAN tidak bisa dibatalkan.',
                ]);
            }
            $peminjaman->update(['status' => Peminjaman::STATUS_DIBATALKAN]);
            return $peminjaman->fresh();
        });
    }

    /**
     * Helper untuk penanda terlambat (dipakai scheduler).
     */
    public function markOverdueIfNeeded(Peminjaman $peminjaman): ?Peminjaman
    {
        if (
            in_array($peminjaman->status, [Peminjaman::STATUS_DIPINJAM, Peminjaman::STATUS_APPROVED], true)
            && now()->greaterThan($peminjaman->tgl_jatuh_tempo)
        ) {
            $peminjaman->update(['status' => Peminjaman::STATUS_TERLAMBAT]);
            return $peminjaman->fresh();
        }
        return null;
    }
}
