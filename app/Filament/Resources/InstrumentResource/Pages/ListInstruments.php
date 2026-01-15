<?php

namespace App\Filament\Resources\InstrumentResource\Pages;

use App\Filament\Resources\InstrumentResource;
use App\Filament\Resources\InstrumentResource\Widgets\InstrumentStatsWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInstruments extends ListRecords
{
    protected static string $resource = InstrumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            InstrumentStatsWidget::class,
        ];
    }
}
