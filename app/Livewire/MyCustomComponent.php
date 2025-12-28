<?php

namespace App\Livewire;

use Filament\Forms;
use Jeffgreco13\FilamentBreezy\Livewire\PersonalInfo;

class MyCustomComponent extends PersonalInfo
{
    // Simpan juga name & email selain field tambahan
    public array $only = ['name', 'email', 'pangkat', 'korps', 'nrp', 'satuan'];

    protected function getProfileFormSchema(): array
    {
        $schema = parent::getProfileFormSchema();

        $extra = Forms\Components\Group::make([
            Forms\Components\TextInput::make('pangkat')->label('Pangkat')->required(),
            Forms\Components\TextInput::make('korps')->label('Korps')->required(),
            Forms\Components\TextInput::make('nrp')->label('NRP')->required(),
            Forms\Components\TextInput::make('satuan')->label('Satuan')->required(),
        ])->columnSpan(2);

        $schema[] = $extra;

        return $schema;
    }
}
