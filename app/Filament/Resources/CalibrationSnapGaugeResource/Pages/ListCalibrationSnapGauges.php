<?php

namespace App\Filament\Resources\CalibrationSnapGaugeResource\Pages;

use App\Filament\Resources\CalibrationSnapGaugeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCalibrationSnapGauges extends ListRecords
{
    protected static string $resource = CalibrationSnapGaugeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
            ->label('New Snap Gauge Calibration')
            ->icon('heroicon-o-plus')
            ->button()
            ->color('primary'),
        ];
    }
}
