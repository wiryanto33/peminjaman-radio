<?php

namespace App\Filament\Resources\KategoriRadioResource\Pages;

use App\Filament\Resources\KategoriRadioResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateKategoriRadio extends CreateRecord
{
    protected static string $resource = KategoriRadioResource::class;
    protected static bool $canCreateAnother = false;

    //customize redirect after create
    public function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
