<?php

namespace App\Filament\Resources\ExternalPurchasingResource\Pages;

use App\Filament\Resources\ExternalPurchasingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExternalPurchasing extends EditRecord
{
    protected static string $resource = ExternalPurchasingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
