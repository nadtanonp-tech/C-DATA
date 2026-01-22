<?php

namespace App\Filament\Resources\ExternalPurchasingResource\Pages;

use App\Filament\Resources\ExternalPurchasingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExternalPurchasing extends CreateRecord
{
    protected static string $resource = ExternalPurchasingResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
