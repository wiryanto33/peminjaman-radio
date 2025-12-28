<?php

namespace App\Observers;

use App\Models\Pengembalian;
use App\Models\Peminjaman;
use App\Models\Radio;
use App\Services\PengembalianService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PengembalianObserver
{
    public function __construct(protected PengembalianService $service) {}

    public function created(Pengembalian $model): void
    {
        // Sinkron status peminjaman & radio
        $this->service->processReturn($model);
    }

    public function updated(Pengembalian $model): void
    {
        // Jika kondisi/radio diubah, proses ulang sinkronisasi
        if ($model->wasChanged(['kondisi_kembali', 'radio_id'])) {
            $this->service->processReturn($model);
        }
    }

    public function deleting(Pengembalian $model): void
    {
        $user = Auth::user();
        $allowed = $user && ($user->hasRole('super_admin') || $user->can('delete_pengembalian'));
        if (! $allowed) {
            throw ValidationException::withMessages([
                'delete' => 'Anda tidak diizinkan menghapus data pengembalian.',
            ]);
        }

        // Rollback status sebelum pengembalian dibuat
        DB::transaction(function () use ($model) {
            $peminjaman = Peminjaman::whereKey($model->peminjaman_id)->lockForUpdate()->first();
            $radio = Radio::whereKey($model->radio_id)->lockForUpdate()->first();

            if ($peminjaman) {
                $peminjaman->status = Peminjaman::STATUS_DIPINJAM;
                $peminjaman->save();
            }
            if ($radio) {
                // Revert stok jika sebelumnya pengembalian berstatus "baik" (stok sempat ditambah)
                if ($peminjaman && $model->kondisi_kembali === \App\Models\Pengembalian::KONDISI_BAIK) {
                    $qty = max(1, (int) ($peminjaman->jumlah ?? 1));
                    $radio->stok = max(0, (int) $radio->stok - $qty);
                }
                $radio->status = (int) $radio->stok === 0 ? Radio::STATUS_STOK_HABIS : Radio::STATUS_TERSEDIA;
                $radio->save();
            }
        });
    }
}
