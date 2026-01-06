<?php

namespace App\Filament\Resources\CalibrationRecordResource\Pages;

use App\Filament\Resources\CalibrationRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCalibrationRecords extends ListRecords
{
    protected static string $resource = CalibrationRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([

                Actions\CreateAction::make('create_vernier_caliper')
                    ->label('Vernier Caliper')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => CalibrationRecordResource::getUrl('create', ['type' => 'vernier_caliper'])),

                Actions\CreateAction::make('create_vernier_digital')
                    ->label('Vernier Digital')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => CalibrationRecordResource::getUrl('create', ['type' => 'vernier_digital'])),
            ])
            ->label('New Instrument Calibration')
            ->icon('heroicon-o-plus')
            ->button()
            ->color('primary'),
        ];
    }
}   