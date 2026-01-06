<?php

namespace App\Filament\Resources\CalibrationPlugGaugeResource\Pages;

use App\Filament\Resources\CalibrationPlugGaugeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCalibrationPlugGauges extends ListRecords
{
    protected static string $resource = CalibrationPlugGaugeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
            ->label('New Plug Gauge Calibration')
            ->icon('heroicon-o-plus')
            ->button()
            ->color('primary'),
        ];
    }
}
