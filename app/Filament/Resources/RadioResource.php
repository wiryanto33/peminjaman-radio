<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RadioResource\Pages;
use App\Filament\Resources\RadioResource\RelationManagers;
use App\Models\Radio;
use Dom\Text;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use App\Models\Peminjaman;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\Group as InfolistGroup;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\BadgeEntry;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;

class RadioResource extends Resource
{
    protected static ?string $model = Radio::class;

    protected static ?string $navigationIcon = 'heroicon-o-radio';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Radio';

    protected static ?string $modelLabel = 'Radio';

    protected static ?string $pluralLabel = 'Radio';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('kategori_id')
                    ->relationship('kategori', 'nama')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('merk')
                    ->maxLength(255),
                Forms\Components\TextInput::make('model')
                    ->maxLength(255),
                Forms\Components\FileUpload::make('image')
                    ->image()
                    ->disk('public')
                    ->directory('radios')
                    ->visibility('public')
                    ->nullable(),
                Forms\Components\TextInput::make('serial_no')
                    ->maxLength(255),
                Select::make('status')
                    ->label('Status Radio')
                    ->default('tersedia')
                    ->options([
                        'tersedia' => 'Tersedia',
                        'dipinjam' => 'Dipinjam',
                        'perbaikan' => 'Perbaikan',
                        'stok_habis' => 'Stok Habis',
                    ])
                    ->native(false)
                    ->required(),
                Select::make('kondisi')
                    ->label('Kondisi Radio')
                    ->default('baik')
                    ->options([
                        'baik' => 'Baik',
                        'rusak_ringan' => 'Rusak Ringan',
                        'rusak_berat' => 'Rusak Berat',
                    ])
                    ->native(false)
                    ->required(),
                TextInput::make('stok')
                    ->label('Stok')
                    ->numeric()
                    ->default(1)
                    ->minValue(0)
                    ->required()
                    ->rule(function (callable $get) {
                        return function (string $attribute, $value, $fail) use ($get) {
                            $total = (int) ($get('stok_total') ?? 0);
                            if ($total > 0 && (int) $value > $total) {
                                $fail('Stok tidak boleh melebihi stok total ('.$total.').');
                            }
                        };
                    }),
                TextInput::make('stok_total')
                    ->label('Stok Total')
                    ->numeric()
                    ->default(1)
                    ->minValue(0)
                    ->required(),
                RichEditor::make('deskripsi')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kategori.nama')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('merk')
                    ->searchable(),
                Tables\Columns\TextColumn::make('model')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('image')
                    ->disk('public')
                    ->label('Foto')
                    ->size(50)
                    ->circular(),
                Tables\Columns\TextColumn::make('serial_no')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn($state) => Str::headline($state))
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'tersedia' => 'success',
                        'dipinjam' => 'warning',
                        'stok_habis' => 'danger',
                        'perbaikan' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('stok')
                    ->label('Stok')
                    ->sortable(),
                TextColumn::make('kondisi')
                    ->label('Kondisi')
                    ->formatStateUsing(fn($state) => Str::headline($state))
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'baik' => 'success',
                        'rusak_ringan' => 'warning',
                        'rusak_berat' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        Radio::STATUS_TERSEDIA => 'Tersedia',
                        Radio::STATUS_DIPINJAM => 'Dipinjam',
                        Radio::STATUS_PERBAIKAN => 'Perbaikan',
                        Radio::STATUS_STOK_HABIS => 'Stok Habis',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),

                Tables\Actions\Action::make('ajukanPeminjaman')
                    ->label('Ajukan Peminjaman')
                    ->icon('heroicon-m-paper-airplane')
                    ->color('primary')
                    ->visible(fn(Radio $record): bool => $record->stok > 0 && $record->status !== Radio::STATUS_PERBAIKAN && (auth()->user()?->hasRole('peminjam') ?? false))
                    ->form([
                        DateTimePicker::make('tgl_pinjam')
                            ->label('Tanggal Pinjam')
                            ->default(now())
                            ->required(),
                        DateTimePicker::make('tgl_jatuh_tempo')
                            ->label('Jatuh Tempo')
                            ->minDate(fn (callable $get) => $get('tgl_pinjam'))
                            ->required(),
                        TextInput::make('jumlah')
                            ->label('Jumlah')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required(),
                        TextInput::make('keperluan')
                            ->label('Keperluan')
                            ->maxLength(255),
                        TextInput::make('lokasi_penggunaan')
                            ->label('Lokasi Penggunaan')
                            ->maxLength(255),
                        Textarea::make('catatan')
                            ->label('Catatan')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data, Radio $record) {
                        $user = auth()->user();
                        if (! $user) {
                            Notification::make()->title('Anda harus login.')->danger()->send();
                            return;
                        }
                        $jumlah = max(1, (int) ($data['jumlah'] ?? 1));
                        if ((int) $record->stok < $jumlah) {
                            Notification::make()->title('Stok tidak mencukupi (tersedia: '.$record->stok.').')->danger()->send();
                            return;
                        }
                        Peminjaman::create([
                            'radio_id' => $record->id,
                            'jumlah' => $jumlah,
                            'peminjam_id' => $user->id,
                            'petugas_id' => null,
                            'tgl_pinjam' => $data['tgl_pinjam'] ?? now(),
                            'tgl_jatuh_tempo' => $data['tgl_jatuh_tempo'] ?? now()->addDay(),
                            'status' => Peminjaman::STATUS_PENDING,
                            'keperluan' => $data['keperluan'] ?? null,
                            'lokasi_penggunaan' => $data['lokasi_penggunaan'] ?? null,
                            'catatan' => $data['catatan'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Pengajuan Peminjaman dibuat')
                            ->body('Menunggu persetujuan petugas.')
                            ->success()
                            ->send();
                    }),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
                Tables\Actions\BulkAction::make('ajukanPeminjaman')
                    ->label('Ajukan Peminjaman (Multi)')
                    ->icon('heroicon-o-plus-circle')
                    ->visible(fn (): bool => (auth()->user()?->hasRole('peminjam') ?? false))
                    ->form([
                        DateTimePicker::make('tgl_pinjam')
                            ->label('Tanggal Pinjam')
                            ->default(now())
                            ->required(),
                        DateTimePicker::make('tgl_jatuh_tempo')
                            ->label('Jatuh Tempo')
                            ->minDate(fn (callable $get) => $get('tgl_pinjam'))
                            ->required(),
                        TextInput::make('jumlah')
                            ->label('Jumlah per Radio')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required(),
                        TextInput::make('keperluan')
                            ->label('Keperluan')
                            ->maxLength(255),
                        TextInput::make('lokasi_penggunaan')
                            ->label('Lokasi Penggunaan')
                            ->maxLength(255),
                        Textarea::make('catatan')
                            ->label('Catatan')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data, Collection $records) {
                        $user = auth()->user();
                        if (! $user) {
                            Notification::make()->title('Anda harus login.')->danger()->send();
                            return;
                        }

                        $created = 0;
                        $skipped = 0;
                        $skippedList = [];

                        foreach ($records as $radio) {
                            // Pastikan stok mencukupi dan tidak perbaikan
                            $jumlah = max(1, (int) ($data['jumlah'] ?? 1));
                            if ((int) $radio->stok < $jumlah || $radio->status === Radio::STATUS_PERBAIKAN) {
                                $skipped++;
                                $skippedList[] = $radio->serial_no ?: ('#'.$radio->id);
                                continue;
                            }

                            try {
                                Peminjaman::create([
                                    'radio_id' => $radio->id,
                                    'jumlah' => $jumlah,
                                    'peminjam_id' => $user->id,
                                    'petugas_id' => null,
                                    'tgl_pinjam' => $data['tgl_pinjam'] ?? now(),
                                    'tgl_jatuh_tempo' => $data['tgl_jatuh_tempo'] ?? now()->addDay(),
                                    'status' => Peminjaman::STATUS_PENDING,
                                    'keperluan' => $data['keperluan'] ?? null,
                                    'lokasi_penggunaan' => $data['lokasi_penggunaan'] ?? null,
                                    'catatan' => $data['catatan'] ?? null,
                                ]);
                                $created++;
                            } catch (\Throwable $e) {
                                $skipped++;
                                $skippedList[] = $radio->serial_no ?: ('#'.$radio->id);
                            }
                        }

                        if ($created > 0) {
                            Notification::make()
                                ->title("$created pengajuan peminjaman dibuat")
                                ->body('Menunggu persetujuan petugas.')
                                ->success()
                                ->send();
                        }

                        if ($skipped > 0) {
                            $detail = $skippedList ? (' ('.implode(', ', array_slice($skippedList, 0, 5)).($skipped > 5 ? ', ...' : '').')') : '';
                            Notification::make()
                                ->title("$skipped radio dilewati")
                                ->body('Beberapa radio tidak tersedia untuk dipinjam'.$detail)
                                ->warning()
                                ->send();
                        }
                    })
                    ->deselectRecordsAfterCompletion(),
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
            'index' => Pages\ListRadios::route('/'),
            'create' => Pages\CreateRadio::route('/create'),
            'view' => Pages\ViewRadio::route('/{record}'),
            'edit' => Pages\EditRadio::route('/{record}/edit'),
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Informasi Radio')
                    ->columns(3)
                    ->schema([
                        ImageEntry::make('image')
                            ->label('Foto')
                            ->getStateUsing(fn($record) => $record->image ? Storage::disk('public')->url($record->image) : null)
                            ->size(160)
                            ->columnSpan(1)
                            ->hiddenLabel(),
                        InfolistGroup::make([
                            TextEntry::make('merk')->label('Merk'),
                            TextEntry::make('model')->label('Model'),
                            TextEntry::make('serial_no')->label('Serial No'),
                            TextEntry::make('kategori.nama')->label('Kategori'),
                        ])->columnSpan(2),
                    ]),

                InfolistSection::make('Status')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Status')
                            ->formatStateUsing(fn($state) => Str::headline($state))
                            ->badge()
                            ->color(fn(string $state) => match ($state) {
                                'tersedia' => 'success',
                                'dipinjam' => 'warning',
                                'perbaikan' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('kondisi')
                            ->label('Kondisi')
                            ->formatStateUsing(fn($state) => Str::headline(str_replace('_', ' ', $state)))
                            ->badge()
                            ->color(fn(string $state) => match ($state) {
                                'baik' => 'success',
                                'rusak_ringan' => 'warning',
                                'rusak_berat' => 'danger',
                                default => 'gray',
                            }),
                    ]),

                InfolistSection::make('Deskripsi')
                    ->schema([
                        TextEntry::make('deskripsi')->html()->hiddenLabel(),
                    ]),
            ]);
    }
}
