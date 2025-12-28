<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PengembalianResource\Pages;
use App\Filament\Resources\PengembalianResource\RelationManagers;
use App\Models\Peminjaman;
use App\Models\Pengembalian;
use App\Models\Radio;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rule;

class PengembalianResource extends Resource
{
    protected static ?string $model = Pengembalian::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-turn-down-right';

    protected static ?string $navigationGroup = 'Data Transaksi';

    protected static ?string $navigationLabel = 'Pengembalian Radio';

    protected static ?string $modelLabel = 'Pengembalian Radio';

    protected static ?string $pluralLabel = 'Pengembalian Radio';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Data Pengembalian')
                    ->columns(2)
                    ->schema([
                        Select::make('peminjaman_id')
                            ->label('Peminjaman')
                            ->relationship('peminjaman', 'kode_peminjaman', function (Builder $query, ?Pengembalian $record) {
                                // Saat create: tampilkan hanya peminjaman berstatus DIPINJAM.
                                // Saat edit: sertakan record terpilih meski statusnya sudah bukan DIPINJAM.
                                if ($record) {
                                    $query->where(function ($q) use ($record) {
                                        $q->where('status', Peminjaman::STATUS_DIPINJAM)
                                            ->orWhere('id', $record->peminjaman_id);
                                    });
                                } else {
                                    $query->where('status', Peminjaman::STATUS_DIPINJAM);
                                }
                            })
                            ->getOptionLabelFromRecordUsing(function (Peminjaman $record) {
                                $kode = $record->kode_peminjaman ?: ('#' . $record->id);
                                $nama = optional($record->peminjam)->name;
                                return trim($kode . ' - ' . $nama);
                            })
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $radioId = optional(Peminjaman::find($state))->radio_id;
                                $set('radio_id', $radioId);
                            })
                            ->rule(fn (?Pengembalian $record) => Rule::unique('pengembalian', 'peminjaman_id')->ignore($record?->id))
                            ->required(),

                        Select::make('radio_id')
                            ->label('Radio')
                            ->options(function (callable $get) {
                                $peminjamanId = $get('peminjaman_id');
                                if (!$peminjamanId) {
                                    return [];
                                }
                                $p = Peminjaman::find($peminjamanId);
                                if (!$p || !$p->radio) {
                                    return [];
                                }
                                $r = $p->radio;
                                $text = ($r->merk . ' ' . $r->model . ' (' . $r->serial_no . ')');
                                return [$r->id => trim($text) ?: ('#' . $r->id)];
                            })
                            ->required()
                            ->disabled(fn(callable $get) => blank($get('peminjaman_id')))
                            ->dehydrated(),

                        Select::make('penerima_id')
                            ->label('Penerima')
                            ->relationship('penerima', 'name', function (Builder $query, ?Pengembalian $record) {
                                $query->where(function ($q) use ($record) {
                                    // Hanya user dengan role petugas / super_admin
                                    $q->whereHas('roles', function ($r) {
                                        $r->whereIn('name', ['super_admin', 'petugas']);
                                    });
                                    // Saat edit: tetap tampilkan penerima yang sudah tersimpan meskipun tidak lolos filter
                                    if ($record && $record->penerima_id) {
                                        $q->orWhere('id', $record->penerima_id);
                                    }
                                });
                            })
                            ->default(fn () => auth()->id())
                            ->disabled(fn (string $operation) => $operation === 'create')
                            ->dehydrated()
                            ->searchable()
                            ->preload()
                            ->required(),

                        DateTimePicker::make('tgl_kembali')
                            ->label('Tanggal Kembali')
                            ->default(now())
                            ->required(),

                        Select::make('kondisi_kembali')
                            ->label('Kondisi Saat Kembali')
                            ->native(false)
                            ->options([
                                Pengembalian::KONDISI_BAIK => 'Baik',
                                Pengembalian::KONDISI_RUSAK_RINGAN => 'Rusak Ringan',
                                Pengembalian::KONDISI_RUSAK_BERAT => 'Rusak Berat',
                                Pengembalian::KONDISI_HILANG => 'Hilang',
                            ])
                            ->required(),

                        Textarea::make('catatan')
                            ->label('Catatan')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('peminjaman.kode_peminjaman')
                    ->label('Kode Peminjaman')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('radio.serial_no')
                    ->label('Radio')
                    ->formatStateUsing(fn($state, Pengembalian $record) => $record->radio
                        ? trim(($record->radio->merk . ' ' . $record->radio->model . ' (' . $record->radio->serial_no . ')'))
                        : '-')
                    ->toggleable(),

                TextColumn::make('penerima.name')
                    ->label('Penerima')
                    ->sortable(),

                TextColumn::make('tgl_kembali')
                    ->label('Tanggal Kembali')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('kondisi_kembali')
                    ->label('Kondisi')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        Pengembalian::KONDISI_BAIK => 'success',
                        Pengembalian::KONDISI_RUSAK_RINGAN => 'warning',
                        Pengembalian::KONDISI_RUSAK_BERAT => 'danger',
                        Pengembalian::KONDISI_HILANG => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('kondisi_kembali')
                    ->label('Kondisi')
                    ->options([
                        Pengembalian::KONDISI_BAIK => 'Baik',
                        Pengembalian::KONDISI_RUSAK_RINGAN => 'Rusak Ringan',
                        Pengembalian::KONDISI_RUSAK_BERAT => 'Rusak Berat',
                        Pengembalian::KONDISI_HILANG => 'Hilang',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->visible(fn () => (auth()->user()?->hasRole('super_admin') ?? false)
                        || (auth()->user()?->can('delete_pengembalian') ?? false)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => (auth()->user()?->hasRole('super_admin') ?? false)
                            || (auth()->user()?->can('delete_pengembalian') ?? false)),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPengembalians::route('/'),
            'create' => Pages\CreatePengembalian::route('/create'),
            'edit' => Pages\EditPengembalian::route('/{record}/edit'),
        ];
    }
}
