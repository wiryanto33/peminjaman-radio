<?php

namespace App\Filament\Widgets;

use App\Models\Peminjaman;
use App\Models\Radio;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Illuminate\Support\Facades\DB;

class RadioStatsOverview extends BaseWidget
{
    protected static ?int $sort = -100; // ensure appears at the top
    protected static ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        $user = auth()->user();
        return (bool) ($user?->hasAnyRole(['super_admin', 'petugas', 'komandan']) ?? true);
    }

    protected function getCards(): array
    {
        $total = Radio::count();

        // ID radio yang sedang aktif dipinjam (ada peminjaman aktif)
        $activeRadioIds = Peminjaman::whereIn('status', [
            Peminjaman::STATUS_DIPINJAM,
            Peminjaman::STATUS_APPROVED,
            Peminjaman::STATUS_TERLAMBAT,
        ])->pluck('radio_id')->unique()->values()->all();

        // Dipinjam: radio yang punya peminjaman aktif
        $dipinjam = count($activeRadioIds);

        // Perbaikan: dari kolom status radio
        $perbaikan = Radio::where('status', Radio::STATUS_PERBAIKAN)->count();

        // Stok habis: radio dengan stok = 0 dan TIDAK sedang dipinjam dan TIDAK perbaikan
        $stokHabis = Radio::where('stok', 0)
            ->whereNotIn('id', $activeRadioIds)
            ->where('status', '!=', Radio::STATUS_PERBAIKAN)
            ->count();

        // Tersedia: total - dipinjam - perbaikan - stokHabis
        $tersedia = max(0, $total - $dipinjam - $perbaikan - $stokHabis);

        $baik = Radio::where('kondisi', Radio::KONDISI_BAIK)->count();
        $rusakRingan = Radio::where('kondisi', Radio::KONDISI_RUSAK_RINGAN)->count();
        $rusakBerat = Radio::where('kondisi', Radio::KONDISI_RUSAK_BERAT)->count();

        return [
            Card::make('Total Radio', (string) $total)->icon('heroicon-o-rectangle-stack'),
            Card::make('Tersedia', (string) $tersedia)->icon('heroicon-o-check-circle')->color('success'),
            Card::make('Dipinjam', (string) $dipinjam)->icon('heroicon-o-clock')->color('warning'),
            Card::make('Perbaikan', (string) $perbaikan)->icon('heroicon-o-wrench')->color('danger'),
            Card::make('Stok Habis', (string) $stokHabis)->icon('heroicon-o-no-symbol')->color('danger'),
            Card::make('Kondisi Baik', (string) $baik)->icon('heroicon-o-check-badge')->color('success'),
            Card::make('Rusak Ringan', (string) $rusakRingan)->icon('heroicon-o-exclamation-triangle')->color('warning'),
            Card::make('Rusak Berat', (string) $rusakBerat)->icon('heroicon-o-x-circle')->color('danger'),
        ];
    }
}
