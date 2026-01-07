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

                Actions\CreateAction::make('create_vernier_special')
                    ->label('Vernier Special')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => CalibrationRecordResource::getUrl('create', ['type' => 'vernier_special'])),

                Actions\CreateAction::make('create_depth_vernier')
                    ->label('Depth Vernier')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => CalibrationRecordResource::getUrl('create', ['type' => 'depth_vernier'])),

                Actions\CreateAction::make('create_vernier_hight_gauge')
                    ->label('Vernier Hight Gauge')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => CalibrationRecordResource::getUrl('create', ['type' => 'vernier_hight_gauge'])),

                Actions\CreateAction::make('create_dial_vernier_hight_gauge')
                    ->label('Dial Vernier Hight Gauge')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => CalibrationRecordResource::getUrl('create', ['type' => 'dial_vernier_hight_gauge'])),

                Actions\CreateAction::make('create_micro_meter')
                    ->label('Micro Meter')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => CalibrationRecordResource::getUrl('create', ['type' => 'micro_meter'])),

                Actions\CreateAction::make('create_dial_caliper')
                    ->label('Dial Caliper')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => CalibrationRecordResource::getUrl('create', ['type' => 'dial_caliper'])),

                Actions\CreateAction::make('create_dial_indicator')
                    ->label('Dial Indicator')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => CalibrationRecordResource::getUrl('create', ['type' => 'dial_indicator'])),

                Actions\CreateAction::make('create_dial_test_indicator')
                    ->label('Dial Test Indicator')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => CalibrationRecordResource::getUrl('create', ['type' => 'dial_test_indicator'])),

                Actions\CreateAction::make('create_thickness_gauge')
                    ->label('Thickness Gauge')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => CalibrationRecordResource::getUrl('create', ['type' => 'thickness_gauge'])),

                Actions\CreateAction::make('create_thickness_caliper')
                    ->label('Thickness Caliper')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => CalibrationRecordResource::getUrl('create', ['type' => 'thickness_caliper'])),

                Actions\CreateAction::make('create_cylinder_gauge')
                    ->label('Cylinder Gauge')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => CalibrationRecordResource::getUrl('create', ['type' => 'cylinder_gauge'])),

                Actions\CreateAction::make('create_chamfer_gauge')
                    ->label('Chamfer Gauge')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => CalibrationRecordResource::getUrl('create', ['type' => 'chamfer_gauge'])),
            ])
            ->label('New Instrument Calibration')
            ->icon('heroicon-o-plus')
            ->button()
            ->color('primary'),
        ];
    }
}   