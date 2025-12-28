<?php

namespace App\Filament\Widgets;

use App\Models\Radio;
use Filament\Widgets\ChartWidget;

class RadioKondisiChart extends ChartWidget
{
    protected static ?string $heading = 'Kondisi Radio';
    protected static ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        $user = auth()->user();
        return (bool) ($user?->hasAnyRole(['super_admin', 'petugas', 'komandan']) ?? true);
    }

    protected function getData(): array
    {
        $baik = Radio::where('kondisi', Radio::KONDISI_BAIK)->count();
        $rr = Radio::where('kondisi', Radio::KONDISI_RUSAK_RINGAN)->count();
        $rb = Radio::where('kondisi', Radio::KONDISI_RUSAK_BERAT)->count();

        return [
            'labels' => ['Baik', 'Rusak Ringan', 'Rusak Berat'],
            'datasets' => [
                [
                    'label' => 'Kondisi',
                    'data' => [$baik, $rr, $rb],
                    'backgroundColor' => ['#16a34a', '#f59e0b', '#dc2626'],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

