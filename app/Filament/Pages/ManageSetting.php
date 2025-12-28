<?php

namespace App\Filament\Pages;

use App\Settings\KaidoSetting;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

class ManageSetting extends SettingsPage
{
    use HasPageShield;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $settings = KaidoSetting::class;

    protected static ?string $navigationGroup = 'Settings';


    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Site Information')->columns(1)->schema([
                    TextInput::make('site_name')
                        ->label('Site Name')
                        ->required(),
                    Toggle::make('site_active')
                        ->label('Site Active'),
                    Toggle::make('registration_enabled')
                        ->label('Registration Enabled'),
                    Toggle::make('password_reset_enabled')
                        ->label('Password Reset Enabled'),
                    Toggle::make('sso_enabled')
                        ->label('SSO Enabled'),
                ]),
                Section::make('Auth Branding')->columns(1)->schema([
                    FileUpload::make('auth_logo_path')
                        ->label('Logo Login & Register')
                        ->image()
                        ->disk('public')
                        ->directory('auth')
                        ->imagePreviewHeight('100'),
                    FileUpload::make('auth_background_path')
                        ->label('Background Login & Register')
                        ->image()
                        ->disk('public')
                        ->directory('auth')
                        ->imagePreviewHeight('200'),
                    TextInput::make('auth_card_opacity')
                        ->label('Transparansi Card Login/Register (%)')
                        ->type('range')
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(5)
                        ->default(90),
                ]),
            ]);
    }
}
