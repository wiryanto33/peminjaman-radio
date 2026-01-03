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

        // Pastikan radio available saat pertama kali membuat (untuk PENDING)
        if ($model->radio_id) {
            $radio = Radio::find($model->radio_id);
            if (!$radio || (int) $radio->stok <= 0) {
                throw ValidationException::withMessages([
                    'radio_id' => 'Stok radio tidak tersedia untuk dibuatkan peminjaman.',
                ]);
            }
            $qty = max(1, (int) ($model->jumlah ?? 1));
            if ((int) $radio->stok < $qty) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Jumlah melebihi stok tersedia ('.$radio->stok.').',
                ]);
            }
        }
    }

    public function created(Peminjaman $model): void
    {
        // Jika langsung dibuat dengan status DIPINJAM, kurangi stok dan set status radio
        if ($model->status === Peminjaman::STATUS_DIPINJAM && $model->radio_id) {
            \DB::transaction(function () use ($model) {
                $radio = Radio::whereKey($model->radio_id)->lockForUpdate()->first();
                if (!$radio) return;
                $qty = max(1, (int) ($model->jumlah ?? 1));
                if ((int) $radio->stok < $qty) {
                    throw ValidationException::withMessages([
                        'radio_id' => 'Stok radio tidak mencukupi.',
                    ]);
                }
                $radio->stok = (int) $radio->stok - $qty;
                $radio->status = (int) $radio->stok === 0 ? Radio::STATUS_STOK_HABIS : Radio::STATUS_TERSEDIA;
                $radio->save();
            });
            // Generate bukti penyerahan PDF (best-effort)
            try {
                app(BuktiPenyerahanService::class)->generate($model);
            } catch (\Throwable $e) {
                \Log::warning('Gagal generate bukti penyerahan: '.$e->getMessage(), [
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
                        'Kode: '.($model->kode_peminjaman ?: ('#'.$model->id))."\n".
                        'Peminjam: '.($model->peminjam?->name ?: '-') . "\n" .
                        'Radio: '.($model->radio?->serial_no ?: '-')

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
        // Cegah ubah radio_id setelah aktif
        if ($model->isDirty('radio_id') && $model->getOriginal('status') !== Peminjaman::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'radio_id' => 'Radio tidak dapat diubah setelah peminjaman berjalan.',
            ]);
        }

        if ($model->isDirty('status')) {
            $from = $model->getOriginal('status');
            $to   = $model->status;

            // Perubahan ke DIKEMBALIKAN diperbolehkan (dipicu oleh proses Pengembalian)

            // Saat mengubah menjadi DIPINJAM, validasi stok dan kurangi stok
            if ($to === Peminjaman::STATUS_DIPINJAM) {
                \DB::transaction(function () use ($model) {
                    $radio = Radio::whereKey($model->radio_id)->lockForUpdate()->first();
                    if (!$radio) {
                        throw ValidationException::withMessages([
                            'radio_id' => 'Radio tidak ditemukan.',
                        ]);
                    }
                    $qty = max(1, (int) ($model->jumlah ?? 1));
                    if ((int) $radio->stok < $qty) {
                        throw ValidationException::withMessages([
                            'radio_id' => 'Stok radio tidak mencukupi untuk dipinjam.',
                        ]);
                    }
                    $radio->stok = (int) $radio->stok - $qty;
                    $radio->status = (int) $radio->stok === 0 ? Radio::STATUS_STOK_HABIS : Radio::STATUS_TERSEDIA;
                    $radio->save();
                });
            }
        }
    }

    public function updated(Peminjaman $model): void
    {
        // Sinkron status radio setelah perubahan berhasil disimpan
        if ($model->wasChanged('status')) {
            switch ($model->status) {
                case Peminjaman::STATUS_DIPINJAM:
                    // Generate bukti penyerahan PDF (best-effort)
                    try {
                        app(BuktiPenyerahanService::class)->generate($model);
                    } catch (\Throwable $e) {
                        \Log::warning('Gagal generate bukti penyerahan: '.$e->getMessage(), [
                            'peminjaman_id' => $model->id,
                        ]);
                    }
                    break;
                case Peminjaman::STATUS_DIBATALKAN:
                    break;
                // APPROVED/TERLAMBAT → tidak ubah status radio di sini
                default:
                    break;
            }

            // Notifikasi ke peminjam saat status berubah
            if ($model->peminjam) {
                $url = PeminjamanResource::getUrl('index');
                $notif = Notification::make()
                    ->title('Status Peminjaman Diperbarui')
                    ->body(
                        'Kode: '.($model->kode_peminjaman ?: ('#'.$model->id))."\n".

                        'Status sekarang: '.ucfirst($model->status)
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
}
