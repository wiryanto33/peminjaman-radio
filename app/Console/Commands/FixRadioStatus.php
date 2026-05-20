<?php

namespace App\Console\Commands;

use App\Models\Peminjaman;
use App\Models\Radio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixRadioStatus extends Command
{
    protected $signature = 'radio:fix-status';
    protected $description = 'Sinkronkan status radio berdasarkan peminjaman aktif yang ada';

    public function handle(): void
    {
        DB::transaction(function () {
            // Kumpulkan semua radio_id yang sedang aktif dipinjam
            $activeRadioIds = Peminjaman::whereIn('status', [
                Peminjaman::STATUS_DIPINJAM,
                Peminjaman::STATUS_APPROVED,
                Peminjaman::STATUS_TERLAMBAT,
            ])->pluck('radio_id')->unique()->all();

            // Update radio yang masuk daftar dipinjam
            if (!empty($activeRadioIds)) {
                foreach ($activeRadioIds as $radioId) {
                    $radio = Radio::find($radioId);
                    if (!$radio) continue;
                    // Jika stok 0 → STOK_HABIS, jika > 0 tapi ada yang dipinjam → DIPINJAM
                    if ((int) $radio->stok === 0) {
                        $radio->status = Radio::STATUS_STOK_HABIS;
                    } else {
                        $radio->status = Radio::STATUS_DIPINJAM;
                    }
                    $radio->save();
                    $this->info("Radio #{$radioId} ({$radio->serial_no}) → {$radio->status}");
                }
            }

            // Update radio yang tidak dipinjam dan statusnya masih DIPINJAM → kembalikan ke TERSEDIA
            Radio::whereNotIn('id', $activeRadioIds)
                ->where('status', Radio::STATUS_DIPINJAM)
                ->each(function (Radio $radio) {
                    $radio->status = $radio->stok > 0 ? Radio::STATUS_TERSEDIA : Radio::STATUS_STOK_HABIS;
                    $radio->save();
                    $this->info("Radio #{$radio->id} ({$radio->serial_no}) dikembalikan → {$radio->status}");
                });
        });

        $this->info('Selesai sinkronisasi status radio.');
    }
}
