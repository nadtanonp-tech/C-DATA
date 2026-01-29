<?php

namespace App\Filament\Resources\ExternalPurchasingResource\Pages;

use App\Filament\Resources\ExternalPurchasingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExternalPurchasings extends ListRecords
{
    use \Filament\Pages\Concerns\ExposesTableToWidgets;

    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }
    
    protected static string $resource = ExternalPurchasingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New In External'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ExternalPurchasingResource\Widgets\StatusWidget::class,
            ExternalPurchasingResource\Widgets\PurchasingPriceComparisonChart::class,
        ];
    }
}
