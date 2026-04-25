<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PeminjamanResource\Pages;
use App\Filament\Resources\PeminjamanResource\RelationManagers;
use App\Models\Peminjaman;
use App\Models\Radio;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class PeminjamanResource extends Resource
{
    protected static ?string $model = Peminjaman::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-turn-down-left';

    protected static ?string $navigationGroup = 'Data Transaksi';

    protected static ?string $navigationLabel = 'Peminjaman Radio';

    protected static ?string $modelLabel = 'Peminjaman Radio';

    protected static ?string $pluralLabel = 'Peminjaman Radio';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Peminjaman')
                    ->columns(2)
                    ->schema([
                        TextInput::make('kode_peminjaman')
                            ->label('Kode Peminjaman')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Terbentuk otomatis saat disimpan')
                            ->maxLength(255),

                        Select::make('radio_id')
                            ->label('Radio')
                            ->options(function (?Peminjaman $record) {
                                $query = Radio::query();
                                if ($record) {
                                    // Saat edit, sertakan radio terpilih meskipun stok 0
                                    $query->where(function ($q) use ($record) {
                                        $q->where('stok', '>', 0)
                                            ->where('status', '!=', Radio::STATUS_PERBAIKAN)
                                            ->orWhere('id', $record->radio_id);
                                    });
                                } else {
                                    $query->where('stok', '>', 0)
                                        ->where('status', '!=', Radio::STATUS_PERBAIKAN);
                                }

                                return $query->orderBy('merk')
                                    ->get()
                                    ->mapWithKeys(function (Radio $r) {
                                        $labelBase = trim(($r->merk . ' ' . $r->model));
                                        $labelStock = ' (Stok: ' . ((int) $r->stok) . ')';
                                        $label = $labelBase ? ($labelBase . ' (' . $r->serial_no . ')' . $labelStock) : (($r->serial_no ?: ('#' . $r->id)) . $labelStock);
                                        return [$r->id => $label];
                                    })
                                    ->all();
                            })
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('jumlah')
                            ->label('Jumlah')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->rule(function (callable $get) {
                                return function (string $attribute, $value, $fail) use ($get) {
                                    $radioId = $get('radio_id');
                                    if (!$radioId) {
                                        return;
                                    }
                                    $radio = Radio::find($radioId);
                                    if ($radio && (int) $value > (int) $radio->stok) {
                                        $fail('Jumlah melebihi stok tersedia (' . $radio->stok . ').');
                                    }
                                };
                            })
                            ->required(),

                        Select::make('peminjam_id')
                            ->label('Peminjam')
                            ->relationship('peminjam', 'name')
                            ->default(fn() => auth()->id())
                            // Hanya disabled jika user BUKAN super_admin atau petugas
                            ->disabled(fn() => !auth()->user()?->hasAnyRole(['super_admin', 'petugas']))
                            ->dehydrated()
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('petugas_id')
                            ->label('Petugas')
                            ->relationship('petugas', 'name', function (Builder $query) {
                                // Tetap filter agar hanya user dengan role petugas/admin yang bisa muncul
                                $query->whereHas('roles', fn($q) => $q->whereIn('name', ['super_admin', 'petugas']));
                            })
                            // 1. Matikan input agar tidak bisa dipilih manual oleh peminjam
                            ->disabled()
                            // 2. Gunakan dehydrated agar nilainya tetap terkirim saat disave oleh petugas
                            ->dehydrated()
                            // 3. Logika pengisian otomatis: Jika yang login adalah petugas/admin,
                            // maka saat mereka mengedit/approve, nama mereka akan masuk ke sini.
                            ->default(fn() => auth()->user()?->hasAnyRole(['super_admin', 'petugas']) ? auth()->id() : null)
                            ->placeholder('Akan terisi otomatis saat disetujui')
                            ->searchable()
                            ->preload(),

                        DateTimePicker::make('tgl_pinjam')
                            ->label('Tanggal Pinjam')
                            ->default(now())
                            ->required(),

                        DateTimePicker::make('tgl_jatuh_tempo')
                            ->label('Jatuh Tempo')
                            ->minDate(fn(callable $get) => $get('tgl_pinjam'))
                            ->required(),
                    ]),

                Section::make('Status & Keterangan')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->native(false)
                            // 1. Set default ke Pending
                            ->default(Peminjaman::STATUS_PENDING)
                            // 2. Disable jika user bukan admin/petugas
                            ->disabled(fn() => !auth()->user()?->hasAnyRole(['super_admin', 'petugas']))
                            // 3. Pastikan tetap terkirim ke database meskipun disabled (agar default masuk)
                            ->dehydrated()
                            ->options(fn(string $operation) => match ($operation) {
                                'create' => [
                                    Peminjaman::STATUS_PENDING => 'Pending',
                                    Peminjaman::STATUS_APPROVED => 'Disetujui',
                                ],
                                default => [
                                    Peminjaman::STATUS_PENDING => 'Pending',
                                    Peminjaman::STATUS_APPROVED => 'Disetujui',
                                    Peminjaman::STATUS_DIPINJAM => 'Dipinjam',
                                    Peminjaman::STATUS_DIBATALKAN => 'Dibatalkan',
                                ],
                            })
                            ->helperText(function () {
                                if (!auth()->user()?->hasAnyRole(['super_admin', 'petugas'])) {
                                    return 'Status default adalah "Pending". Hanya petugas yang dapat mengubah status.';
                                }
                                return 'Status "Dikembalikan" dan "Terlambat" ditetapkan otomatis oleh sistem.';
                            })
                            ->required(),


                        TextInput::make('keperluan')
                            ->label('Keperluan')
                            ->maxLength(255),

                        TextInput::make('lokasi_penggunaan')
                            ->label('Lokasi Penggunaan')
                            ->maxLength(255),

                        FileUpload::make('surat_permohonan')
                            ->label('Upload Surat Permohonan')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(5120)
                            ->disk('public')
                            ->directory('peminjaman_surat')
                            ->required(fn() => auth()->user()?->hasRole('peminjam'))
                            ->downloadable()
                            ->openable()
                            ->helperText('Unggah surat permohonan peminjaman. Format: JPEG, PNG, PDF. Maksimal ukuran file 5MB.'),

                        FileUpload::make('file')
                            ->label('Upload Bukti Peminjaman')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(5120)
                            ->disk('public')
                            ->directory('peminjaman_files')
                            ->downloadable()
                            ->openable()
                            ->helperText('Unggah bukti peminjaman Anda (surat tugas, izin, dll). Format: JPEG, PNG, PDF. Maksimal ukuran file 5MB.'),

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
                TextColumn::make('kode_peminjaman')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('radio.serial_no')
                    ->label('Radio')
                    ->formatStateUsing(fn($state, Peminjaman $record) => $record->radio
                        ? trim(($record->radio->merk . ' ' . $record->radio->model . ' (' . $record->radio->serial_no . ')'))
                        : '-')
                    ->toggleable(),

                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->sortable(),

                TextColumn::make('peminjam.name')
                    ->label('Peminjam')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('petugas.name')
                    ->label('Petugas')
                    ->sortable(),

                TextColumn::make('tgl_pinjam')
                    ->label('Tgl Pinjam')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('tgl_jatuh_tempo')
                    ->label('Jatuh Tempo')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        Peminjaman::STATUS_PENDING => 'gray',
                        Peminjaman::STATUS_APPROVED => 'info',
                        Peminjaman::STATUS_DIPINJAM => 'warning',
                        Peminjaman::STATUS_DIKEMBALIKAN => 'success',
                        Peminjaman::STATUS_DIBATALKAN => 'danger',
                        Peminjaman::STATUS_TERLAMBAT => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('surat_permohonan')
                    ->label('Surat Permohonan')
                    ->formatStateUsing(fn ($state) => $state ? 'Tersedia' : 'Tidak Ada')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->toggleable(),

                TextColumn::make('keperluan')
                    ->label('Keperluan')
                    ->toggleable()
                    ->limit(30),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        Peminjaman::STATUS_PENDING => 'Pending',
                        Peminjaman::STATUS_APPROVED => 'Disetujui',
                        Peminjaman::STATUS_DIPINJAM => 'Dipinjam',
                        Peminjaman::STATUS_DIKEMBALIKAN => 'Dikembalikan',
                        Peminjaman::STATUS_DIBATALKAN => 'Dibatalkan',
                        Peminjaman::STATUS_TERLAMBAT => 'Terlambat',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Action::make('download_bukti')
                    ->label('Unduh Bukti')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn(Peminjaman $record): bool => in_array($record->status, [Peminjaman::STATUS_DIPINJAM, Peminjaman::STATUS_DIKEMBALIKAN, Peminjaman::STATUS_TERLAMBAT], true))
                    ->url(fn(Peminjaman $record) => route('peminjaman.downloadBukti', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->visible(fn(Peminjaman $record): bool => in_array($record->status, [
                        Peminjaman::STATUS_DIKEMBALIKAN,
                        Peminjaman::STATUS_DIBATALKAN,
                    ], true)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListPeminjamen::route('/'),
            'create' => Pages\CreatePeminjaman::route('/create'),
            'edit' => Pages\EditPeminjaman::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        if ($user && $user->hasRole('peminjam') && ! $user->hasRole('super_admin')) {
            $query->where('peminjam_id', $user->id);
        }

        return $query;
    }
}
