<?php

namespace App\Filament\Resources\KategoriRadioResource\Pages;

use App\Filament\Resources\KategoriRadioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKategoriRadios extends ListRecords
{
    protected static string $resource = KategoriRadioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
