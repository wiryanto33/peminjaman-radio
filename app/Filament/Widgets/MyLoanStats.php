<?php

namespace App\Filament\Widgets;

use App\Models\Peminjaman;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class MyLoanStats extends BaseWidget
{
    protected static ?int $sort = 150;
    protected static ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        $user = auth()->user();
        return (bool) ($user?->hasRole('peminjam') && ! $user?->hasAnyRole(['super_admin','petugas', 'komandan']));
    }

    protected function getCards(): array
    {
        $user = auth()->user();
        $activeCount = Peminjaman::where('peminjam_id', $user->id)
            ->where('status', Peminjaman::STATUS_DIPINJAM)
            ->count();

        $overdueCount = Peminjaman::where('peminjam_id', $user->id)
            ->where('status', Peminjaman::STATUS_DIPINJAM)
            ->where('tgl_jatuh_tempo', '<', now())
            ->count();

        $historyCount = Peminjaman::where('peminjam_id', $user->id)->count();

        return [
            Card::make('Peminjaman Aktif', (string) $activeCount)
                ->description('Sedang Anda pinjam saat ini')
                ->icon('heroicon-o-clock')
                ->color('warning'),
            Card::make('Sudah Jatuh Tempo', (string) $overdueCount)
                ->description('Segera kembalikan ke petugas')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($overdueCount > 0 ? 'danger' : 'success'),
            Card::make('Total Riwayat', (string) $historyCount)
                ->description('Semua peminjaman Anda')
                ->icon('heroicon-o-rectangle-stack')
                ->color('gray'),
        ];
    }
}

