<?php

namespace App\Filament\Resources\RadioResource\Pages;

use App\Filament\Resources\RadioResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateRadio extends CreateRecord
{
    protected static string $resource = RadioResource::class;
    protected static bool $canCreateAnother = false;

    //customize redirect after create
    public function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
