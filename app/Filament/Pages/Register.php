<?php

namespace App\Filament\Pages;

use Filament\Pages\Auth\Register as BaseRegister;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Filament\Http\Responses\Auth\Contracts\RegistrationResponse;
use Illuminate\Database\Eloquent\Model;
use Filament\Facades\Filament;

class Register extends BaseRegister
{
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        Section::make('Informasi Personal')
                            ->schema([
                                $this->getNameFormComponent()
                                    ->unique(ignoreRecord: true),
                                $this->getEmailFormComponent()
                                    ->unique(ignoreRecord: true),
                            ])->columns(2),

                        Section::make('Informasi Dinas')
                            ->schema([
                                TextInput::make('pangkat')
                                    ->label('Pangkat')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('korps')
                                    ->label('Korps')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('nrp')
                                    ->label('NRP')
                                    ->required()
                                    ->numeric()
                                    ->unique(table: \App\Models\User::class, column: 'nrp', ignoreRecord: true)
                                    ->validationMessages([
                                        'unique' => 'NRP ini sudah terdaftar.',
                                    ])
                                    ->maxLength(255),
                                TextInput::make('satuan')
                                    ->label('Satuan')
                                    ->required()
                                    ->maxLength(255),
                            ])->columns(2),

                        Section::make('Upload Identitas')
                            ->schema([
                                $this->getDocumentFormComponent(),
                            ]),

                        Section::make('Keamanan')
                            ->schema([
                                $this->getPasswordFormComponent(),
                                $this->getPasswordConfirmationFormComponent(),
                            ]),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getDocumentFormComponent(): Component
    {
        return FileUpload::make('file')
            ->label('Upload File Identitas')
            ->required()
            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
            ->maxSize(5120)
            ->disk('public')
            ->directory('documents')
            ->image()
            ->imageEditor()
            ->helperText('Unggah file identitas Anda (KTP/SIM/Kartu Identitas). Format: JPEG, PNG, PDF. Maksimal ukuran file 5MB.')
            ->columnSpanFull();
    }

    protected function handleRegistration(array $data): Model
    {
        $data['status'] = false;
        return $this->getUserModel()::create($data);
    }

    protected function mutateFormDataBeforeRegister(array $data): array
    {
        $data['status'] = false;
        return $data;
    }

    // Override method ini untuk custom redirect setelah registrasi
    protected function getRegistrationFormComponent(): Component
    {
        return parent::getRegistrationFormComponent();
    }
}
