<?php

namespace App\Filament\Widgets;

use App\Models\Peminjaman;
use App\Models\Radio;
use Filament\Widgets\ChartWidget;

class RadioStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Status Radio';
    protected static ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        $user = auth()->user();
        return (bool) ($user?->hasAnyRole(['super_admin', 'petugas', 'komandan']) ?? true);
    }

    protected function getData(): array
    {
        $total = Radio::count();

        // ID radio yang sedang aktif dipinjam (ada peminjaman aktif)
        $activeRadioIds = Peminjaman::whereIn('status', [
            Peminjaman::STATUS_DIPINJAM,
            Peminjaman::STATUS_APPROVED,
            Peminjaman::STATUS_TERLAMBAT,
        ])->pluck('radio_id')->unique()->values()->all();

        $dipinjam  = count($activeRadioIds);
        $perbaikan = Radio::where('status', Radio::STATUS_PERBAIKAN)->count();
        $stokHabis = Radio::where('stok', 0)
            ->whereNotIn('id', $activeRadioIds)
            ->where('status', '!=', Radio::STATUS_PERBAIKAN)
            ->count();
        $tersedia  = max(0, $total - $dipinjam - $perbaikan - $stokHabis);

        return [
            'labels'   => ['Tersedia', 'Dipinjam', 'Perbaikan', 'Stok Habis'],
            'datasets' => [
                [
                    'label'           => 'Status',
                    'data'            => [$tersedia, $dipinjam, $perbaikan, $stokHabis],
                    'backgroundColor' => ['#10b981', '#f59e0b', '#ef4444', '#dc2626'],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
