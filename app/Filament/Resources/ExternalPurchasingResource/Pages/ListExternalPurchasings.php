<?php

namespace App\Filament\Resources\ExternalPurchasingResource\Pages;

use App\Filament\Resources\ExternalPurchasingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExternalPurchasings extends ListRecords
{
    protected static string $resource = ExternalPurchasingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('สร้างใบส่งสอบเทียบ'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ExternalPurchasingResource\Widgets\StatusWidget::class,
        ];
    }
}
