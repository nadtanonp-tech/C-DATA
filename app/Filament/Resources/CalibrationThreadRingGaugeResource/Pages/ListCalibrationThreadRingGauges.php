<?php

namespace App\Filament\Resources\CalibrationThreadRingGaugeResource\Pages;

use App\Filament\Resources\CalibrationThreadRingGaugeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCalibrationThreadRingGauges extends ListRecords
{
    protected static string $resource = CalibrationThreadRingGaugeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
