<?php

namespace App\Filament\Widgets;

use App\Models\Peminjaman;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class MyLoanHistoryTable extends BaseWidget
{
    protected static ?string $heading = 'Riwayat Peminjaman Saya';
    protected static ?int $sort = 200;
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();
        return (bool) ($user?->hasRole('peminjam') && ! $user?->hasAnyRole(['super_admin','petugas']));
    }

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();
        return Peminjaman::query()
            ->with(['radio'])
            ->where('peminjam_id', $user->id)
            ->latest('tgl_pinjam');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('kode_peminjaman')->label('Kode')->sortable()->searchable(),
            TextColumn::make('radio.serial_no')
                ->label('Radio')
                ->formatStateUsing(fn ($state, $record) => $record->radio
                    ? trim(($record->radio->merk.' '.$record->radio->model.' ('.$record->radio->serial_no.')'))
                    : '-')
                ->toggleable(),
            TextColumn::make('tgl_pinjam')->label('Tgl Pinjam')->dateTime('d M Y H:i')->sortable(),
            TextColumn::make('tgl_jatuh_tempo')
                ->label('Jatuh Tempo')
                ->dateTime('d M Y H:i')
                ->sortable()
                ->color(fn ($state) => $state && now()->gt($state) ? 'danger' : null)
                ->description(fn ($state) => $state && now()->gt($state) ? 'Sudah jatuh tempo' : null),
            TextColumn::make('status')
                ->badge()
                ->color(fn (string $state) => match ($state) {
                    Peminjaman::STATUS_PENDING => 'gray',
                    Peminjaman::STATUS_APPROVED => 'info',
                    Peminjaman::STATUS_DIPINJAM => 'warning',
                    Peminjaman::STATUS_DIKEMBALIKAN => 'success',
                    Peminjaman::STATUS_DIBATALKAN => 'danger',
                    Peminjaman::STATUS_TERLAMBAT => 'danger',
                    default => 'gray',
                })
                ->sortable(),
        ];
    }
}

