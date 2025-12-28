<?php

namespace App\Filament\Resources\KategoriRadioResource\Pages;

use App\Filament\Resources\KategoriRadioResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditKategoriRadio extends EditRecord
{
    protected static string $resource = KategoriRadioResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }

    //customize redirect after create
    public function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
