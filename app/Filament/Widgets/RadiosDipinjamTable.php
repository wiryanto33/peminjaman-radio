<?php

namespace App\Filament\Widgets;

use App\Models\Peminjaman;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class RadiosDipinjamTable extends BaseWidget
{
    protected static ?string $heading = 'Radio Sedang Dipinjam';
    protected static ?int $sort = 10;
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();
        return (bool) ($user?->hasAnyRole(['super_admin', 'petugas', 'komandan']) ?? true);
    }

    protected function getTableQuery(): Builder
    {
        return Peminjaman::query()
            ->with(['radio', 'peminjam'])
            ->where('status', Peminjaman::STATUS_DIPINJAM)
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
            TextColumn::make('peminjam.name')->label('Peminjam')->sortable()->searchable(),
            TextColumn::make('tgl_pinjam')->label('Tgl Pinjam')->dateTime('d M Y H:i')->sortable(),
            TextColumn::make('tgl_jatuh_tempo')->label('Jatuh Tempo')->dateTime('d M Y H:i')->sortable(),
        ];
    }
}
