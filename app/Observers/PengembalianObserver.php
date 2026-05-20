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
        // Jika kondisi dikembalikan diubah, proses ulang sinkronisasi
        if ($model->wasChanged(['kondisi_kembali', 'radio_id'])) {
            $this->service->processReturn($model);
        }
    }

    public function deleting(Pengembalian $model): void
    {
        $user = Auth::user();
        $allowed = $user && ($user->hasRole('super_admin') || $user->can('delete_pengembalian'));
        if (!$allowed) {
            throw ValidationException::withMessages([
                'delete' => 'Anda tidak diizinkan menghapus data pengembalian.',
            ]);
        }

        // Rollback: batalkan pengembalian → radio kembali ke status DIPINJAM
        // (karena pengembalian dihapus, berarti radio dianggap masih dipinjam)
        DB::transaction(function () use ($model) {
            $peminjaman = Peminjaman::whereKey($model->peminjaman_id)->lockForUpdate()->first();
            $radio      = Radio::whereKey($model->radio_id)->lockForUpdate()->first();

            if ($peminjaman) {
                $peminjaman->status = Peminjaman::STATUS_DIPINJAM;
                $peminjaman->save();
            }

            if ($radio) {
                // Radio kembali ke status DIPINJAM (unit fisik masih di tangan peminjam)
                $radio->status = Radio::STATUS_DIPINJAM;
                $radio->stok   = 0;
                $radio->save();
            }
        });
    }
}
