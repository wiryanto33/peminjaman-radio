<?php

namespace App\Observers;

use App\Models\Peminjaman;
use App\Models\Radio;
use Illuminate\Validation\ValidationException;
use App\Services\BuktiPenyerahanService;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action as NotificationAction;
use App\Models\User;
use App\Notifications\NowDatabaseNotification;
use App\Filament\Resources\PeminjamanResource;
use Illuminate\Support\Str;

class PeminjamanObserver
{
    public function creating(Peminjaman $model): void
    {
        // Set default status bila kosong
        if (blank($model->status)) {
            $model->status = Peminjaman::STATUS_PENDING;
        }

        // Generate kode otomatis bila kosong
        if (blank($model->kode_peminjaman)) {
            $model->kode_peminjaman = Peminjaman::generateKode();
        }

        // Setiap Radio = 1 unit fisik dengan serial_no unik.
        // Validasi: radio harus dalam kondisi TERSEDIA (bukan dipinjam/perbaikan)
        if ($model->radio_id) {
            $radio = Radio::find($model->radio_id);
            if (!$radio) {
                throw ValidationException::withMessages([
                    'radio_id' => 'Radio tidak ditemukan.',
                ]);
            }
            if ($radio->status === Radio::STATUS_PERBAIKAN) {
                throw ValidationException::withMessages([
                    'radio_id' => 'Radio sedang dalam perbaikan dan tidak dapat dipinjam.',
                ]);
            }
            if ($radio->status === Radio::STATUS_DIPINJAM) {
                throw ValidationException::withMessages([
                    'radio_id' => 'Radio (serial: ' . $radio->serial_no . ') sedang dipinjam oleh orang lain.',
                ]);
            }
            if ($radio->stok <= 0 || $radio->status === Radio::STATUS_STOK_HABIS) {
                throw ValidationException::withMessages([
                    'radio_id' => 'Radio tidak tersedia untuk dipinjam.',
                ]);
            }
        }
    }

    public function created(Peminjaman $model): void
    {
        // Jika langsung dibuat dengan status DIPINJAM, ubah status radio
        if ($model->status === Peminjaman::STATUS_DIPINJAM && $model->radio_id) {
            $this->setRadioDipinjam($model);

            // Generate bukti penyerahan PDF (best-effort)
            try {
                app(BuktiPenyerahanService::class)->generate($model);
            } catch (\Throwable $e) {
                \Log::warning('Gagal generate bukti penyerahan: ' . $e->getMessage(), [
                    'peminjaman_id' => $model->id,
                ]);
            }
        }

        // Notifikasi ke admin saat ada peminjaman baru berstatus PENDING dari peminjam
        if ($model->status === Peminjaman::STATUS_PENDING) {
            $admins = User::role('super_admin')->get();
            if ($admins->isNotEmpty()) {
                $url = PeminjamanResource::getUrl('edit', ['record' => $model]);
                $notif = Notification::make()
                    ->title('Permohonan Peminjaman Baru')
                    ->body(
                        'Kode: ' . ($model->kode_peminjaman ?: ('#' . $model->id)) . "\n" .
                        'Peminjam: ' . ($model->peminjam?->name ?: '-') . "\n" .
                        'Radio: ' . ($model->radio?->serial_no ?: '-')
                    )
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->actions([
                        NotificationAction::make('review')
                            ->label('Tinjau')
                            ->url($url)
                            ->openUrlInNewTab(),
                    ]);

                $data = $notif->toArray();
                $data['format'] = 'filament';
                $data['duration'] = 'persistent';
                unset($data['id']);

                foreach ($admins as $admin) {
                    $admin->notify(new NowDatabaseNotification($data));
                }
            }
        }
    }

    public function updating(Peminjaman $model): void
    {
        // Cegah ubah radio_id setelah peminjaman aktif
        if ($model->isDirty('radio_id') && $model->getOriginal('status') !== Peminjaman::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'radio_id' => 'Radio tidak dapat diubah setelah peminjaman berjalan.',
            ]);
        }

        if ($model->isDirty('status')) {
            $to = $model->status;

            // Validasi: radio harus tersedia sebelum diubah ke DIPINJAM
            if ($to === Peminjaman::STATUS_DIPINJAM) {
                $radio = Radio::find($model->radio_id);
                if (!$radio) {
                    throw ValidationException::withMessages([
                        'radio_id' => 'Radio tidak ditemukan.',
                    ]);
                }
                // Boleh jika masih TERSEDIA atau APPROVED (stok > 0)
                if ($radio->status === Radio::STATUS_PERBAIKAN) {
                    throw ValidationException::withMessages([
                        'radio_id' => 'Radio sedang dalam perbaikan, tidak bisa dipinjam.',
                    ]);
                }
                if ($radio->stok <= 0 && $radio->status !== Radio::STATUS_DIPINJAM) {
                    throw ValidationException::withMessages([
                        'radio_id' => 'Radio tidak tersedia (stok habis).',
                    ]);
                }
            }
        }
    }

    public function updated(Peminjaman $model): void
    {
        if ($model->wasChanged('status')) {
            $to = $model->status;

            // → DIPINJAM: tandai radio sebagai dipinjam (stok = 0)
            if ($to === Peminjaman::STATUS_DIPINJAM) {
                $this->setRadioDipinjam($model);

                // Generate bukti penyerahan PDF (best-effort)
                try {
                    app(BuktiPenyerahanService::class)->generate($model);
                } catch (\Throwable $e) {
                    \Log::warning('Gagal generate bukti penyerahan: ' . $e->getMessage(), [
                        'peminjaman_id' => $model->id,
                    ]);
                }
            }

            // → DIBATALKAN: kembalikan radio ke status tersedia (jika sebelumnya sudah dipinjam)
            if ($to === Peminjaman::STATUS_DIBATALKAN) {
                $from = $model->getOriginal('status');
                // Hanya kembalikan jika sebelumnya sudah dipinjam (bukan sekedar pending/approved)
                if ($from === Peminjaman::STATUS_DIPINJAM) {
                    $this->setRadioTersedia($model->radio_id);
                }
            }

            // Notifikasi ke peminjam saat status berubah
            if ($model->peminjam) {
                $url = PeminjamanResource::getUrl('index');
                $notif = Notification::make()
                    ->title('Status Peminjaman Diperbarui')
                    ->body(
                        'Kode: ' . ($model->kode_peminjaman ?: ('#' . $model->id)) . "\n" .
                        'Status sekarang: ' . ucfirst($model->status)
                    )
                    ->icon('heroicon-o-information-circle')
                    ->actions([
                        NotificationAction::make('lihat')
                            ->label('Lihat')
                            ->url($url)
                            ->openUrlInNewTab(),
                    ]);

                $data = $notif->toArray();
                $data['format'] = 'filament';
                $data['duration'] = 'persistent';
                unset($data['id']);

                $model->peminjam->notify(new NowDatabaseNotification($data));
            }
        }
    }

    public function deleting(Peminjaman $model): void
    {
        // Batasi penghapusan agar riwayat tetap konsisten
        if (in_array($model->status, [
            Peminjaman::STATUS_PENDING,
            Peminjaman::STATUS_APPROVED,
            Peminjaman::STATUS_DIPINJAM,
            Peminjaman::STATUS_TERLAMBAT,
        ], true)) {
            throw ValidationException::withMessages([
                'delete' => 'Peminjaman aktif tidak boleh dihapus. Batalkan atau selesaikan pengembalian.',
            ]);
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    /**
     * Tandai radio sebagai DIPINJAM.
     * Karena 1 Radio = 1 unit fisik, stok langsung jadi 0 dan status = dipinjam.
     */
    private function setRadioDipinjam(Peminjaman $model): void
    {
        \DB::transaction(function () use ($model) {
            $radio = Radio::whereKey($model->radio_id)->lockForUpdate()->first();
            if (!$radio) return;

            $radio->status = Radio::STATUS_DIPINJAM;
            $radio->stok   = 0;
            $radio->save();
        });
    }

    /**
     * Kembalikan radio ke status TERSEDIA.
     * Dipanggil saat peminjaman dibatalkan.
     */
    private function setRadioTersedia(int $radioId): void
    {
        \DB::transaction(function () use ($radioId) {
            $radio = Radio::whereKey($radioId)->lockForUpdate()->first();
            if (!$radio) return;

            // Hanya kembalikan jika statusnya DIPINJAM (jangan overwrite PERBAIKAN)
            if ($radio->status === Radio::STATUS_DIPINJAM) {
                $radio->status = Radio::STATUS_TERSEDIA;
                $radio->stok   = 1;
                $radio->save();
            }
        });
    }
}
