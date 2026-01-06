<?php

namespace App\Filament\Resources\CalibrationThreadPlugGaugeResource\Pages;

use App\Filament\Resources\CalibrationThreadPlugGaugeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCalibrationThreadPlugGauges extends ListRecords
{
    protected static string $resource = CalibrationThreadPlugGaugeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
            ->label('New Thread Plug Gauge Calibration')
            ->icon('heroicon-o-plus')
            ->button()
            ->color('primary'),
        ];
    }
}
