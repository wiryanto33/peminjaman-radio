<?php

namespace App\Filament\Widgets;

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
        $tersedia = Radio::where('status', Radio::STATUS_TERSEDIA)->count();
        $dipinjam = Radio::where('status', Radio::STATUS_DIPINJAM)->count();
        $perbaikan = Radio::where('status', Radio::STATUS_PERBAIKAN)->count();
        $stokHabis = Radio::where('status', Radio::STATUS_STOK_HABIS)->count();

        return [
            'labels' => ['Tersedia', 'Dipinjam', 'Perbaikan', 'Stok Habis'],
            'datasets' => [
                [
                    'label' => 'Status',
                    'data' => [$tersedia, $dipinjam, $perbaikan, $stokHabis],
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
