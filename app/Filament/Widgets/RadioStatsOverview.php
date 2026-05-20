<?php

namespace App\Filament\Widgets;

use App\Models\Radio;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class RadioStatsOverview extends BaseWidget
{
    protected static ?int $sort = -100;
    protected static ?string $pollingInterval = '15s';

    public static function canView(): bool
    {
        $user = auth()->user();
        return (bool) ($user?->hasAnyRole(['super_admin', 'petugas', 'komandan']) ?? true);
    }

    protected function getCards(): array
    {
        // Setiap Radio = 1 unit fisik dengan serial_no unik.
        // Status radio mencerminkan kondisi UNIT tersebut secara langsung.
        $total     = Radio::count();
        $tersedia  = Radio::where('status', Radio::STATUS_TERSEDIA)->count();
        $dipinjam  = Radio::where('status', Radio::STATUS_DIPINJAM)->count();
        $perbaikan = Radio::where('status', Radio::STATUS_PERBAIKAN)->count();
        $stokHabis = Radio::where('status', Radio::STATUS_STOK_HABIS)->count();

        $baik        = Radio::where('kondisi', Radio::KONDISI_BAIK)->count();

        return [
            Card::make('Total Radio', (string) $total)->icon('heroicon-o-rectangle-stack'),
            Card::make('Tersedia', (string) $tersedia)->icon('heroicon-o-check-circle')->color('success'),
            Card::make('Dipinjam', (string) $dipinjam)->icon('heroicon-o-clock')->color('warning'),
            Card::make('Perbaikan', (string) $perbaikan)->icon('heroicon-o-wrench')->color('danger'),
            Card::make('Stok Habis', (string) $stokHabis)->icon('heroicon-o-no-symbol')->color('danger'),
            Card::make('Kondisi Baik', (string) $baik)->icon('heroicon-o-check-badge')->color('success'),
        ];
    }
}
