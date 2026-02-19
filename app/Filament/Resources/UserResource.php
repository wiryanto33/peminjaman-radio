<?php

namespace App\Filament\Resources;

use App\Models\User;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Select;
use App\Filament\Exports\UserExporter;
use App\Filament\Imports\UserImporter;
use Filament\Forms\Components\Section;
use Filament\Support\Enums\FontWeight;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Group as InfolistGroup;
use Illuminate\Support\Facades\Storage;
use Filament\Tables\Actions\ExportBulkAction;
use App\Filament\Resources\UserResource\Pages;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Notifications\Notification;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $pluralLabel = 'Pengguna';



    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Personal Information')
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        TextInput::make('email')
                            ->email()
                            ->required(),
                    ])->columns(2),

                Section::make('Service Information')
                    ->schema([
                        TextInput::make('pangkat')
                            ->required(),
                        TextInput::make('korps')
                            ->required(),
                        TextInput::make('nrp')
                            ->required(),
                        TextInput::make('satuan')
                            ->required(),
                    ])->columns(2),

                Section::make('Validasi Pengguna')
                    ->schema([
                        FileUpload::make('file')
                            ->label('Upload File Identitas')
                            ->directory('documents')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                            ->disk('public')
                            ->required(),
                        Toggle::make('status')
                            ->label('Status Verifikasi')
                            ->onColor('success')
                            ->offColor('danger')
                            ->onIcon('heroicon-o-check-circle')
                            ->offIcon('heroicon-o-x-circle')
                            ->required(),
                    ]),

                Section::make('Security')
                    ->schema([
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required(fn(string $operation): bool => $operation === 'create')
                            ->dehydrateStateUsing(fn($state) => filled($state) ? $state : null)
                            ->dehydrated(fn($state) => filled($state)),
                    ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return true;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\ImageColumn::make('avatar_url')
                        ->searchable()
                        ->circular()
                        ->grow(false)
                        ->getStateUsing(fn($record) => $record->avatar_url
                            ? Storage::disk('public')->url($record->avatar_url)
                            : ("https://ui-avatars.com/api/?name=" . urlencode($record->name))),
                    Tables\Columns\TextColumn::make('name')
                        ->searchable()
                        ->weight(FontWeight::Bold),
                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('roles.name')
                            ->searchable()
                            ->icon('heroicon-o-shield-check')
                            ->grow(false),
                        Tables\Columns\TextColumn::make('email')
                            ->icon('heroicon-m-envelope')
                            ->searchable()
                            ->grow(false),
                        // TAMBAHAN: Tampilkan status verifikasi
                        Tables\Columns\IconColumn::make('status')
                            ->label('Status')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                    ])->alignStart()->visibleFrom('lg')->space(1)
                ]),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
                // TAMBAHAN: Filter berdasarkan status
                SelectFilter::make('status')
                    ->label('Status Verifikasi')
                    ->options([
                        1 => 'Terverifikasi',
                        0 => 'Belum Terverifikasi',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                // TAMBAHAN: Quick action untuk verifikasi
                Action::make('verify')
                    ->label('Verifikasi')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => true]);

                        Notification::make()
                            ->title('Berhasil')
                            ->body('User berhasil diverifikasi')
                            ->success()
                            ->send();
                    })
                    ->visible(fn($record) => !$record->status),
                Action::make('Set Role')
                    ->icon('heroicon-m-adjustments-vertical')
                    ->form([
                        Select::make('role')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->required()
                            ->searchable()
                            ->preload()
                            ->optionsLimit(10)
                            ->getOptionLabelFromRecordUsing(fn($record) => $record->name),
                    ]),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(UserExporter::class),
                ImportAction::make()
                    ->importer(UserImporter::class)
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    // TAMBAHAN: Bulk action untuk verifikasi
                    Tables\Actions\BulkAction::make('verify')
                        ->label('Verifikasi User Terpilih')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update(['status' => true]);

                            Notification::make()
                                ->title('Berhasil')
                                ->body('User terpilih berhasil diverifikasi')
                                ->success()
                                ->send();
                        }),
                ]),
                ExportBulkAction::make()
                    ->exporter(UserExporter::class)
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('User Information')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')->label('Name'),
                        TextEntry::make('email')->label('Email'),
                        TextEntry::make('pangkat')->label('Pangkat'),
                        TextEntry::make('korps')->label('Korps'),
                        TextEntry::make('nrp')->label('NRP'),
                        TextEntry::make('satuan')->label('Satuan'),
                    ]),

                // TAMBAHAN: Section untuk file dan status
                InfolistSection::make('Verification Information')
                    ->columns(2)
                    ->schema([
                        ImageEntry::make('file')
                            ->label('File Identitas')
                            ->disk('public')
                            ->height(200),
                        TextEntry::make('status')
                            ->label('Status Verifikasi')
                            ->badge()
                            ->color(fn($state) => $state ? 'success' : 'danger')
                            ->formatStateUsing(fn($state) => $state ? 'Terverifikasi' : 'Belum Terverifikasi'),
                    ]),
            ]);
    }
}
