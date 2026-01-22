<?php

namespace App\Filament\Resources\GaugeCalibrationResource\Pages;

use App\Filament\Resources\GaugeCalibrationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGaugeCalibrations extends ListRecords
{
    protected static string $resource = GaugeCalibrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\CreateAction::make('create_k_gauge')
                    ->label('K-Gauge')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => GaugeCalibrationResource::getUrl('create', ['type' => 'KGauge'])),
                
                Actions\CreateAction::make('create_snap_gauge')
                    ->label('Snap Gauge')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => GaugeCalibrationResource::getUrl('create', ['type' => 'SnapGauge'])),
                
                Actions\CreateAction::make('create_plug_gauge')
                    ->label('Plug Gauge')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => GaugeCalibrationResource::getUrl('create', ['type' => 'PlugGauge'])),

                Actions\CreateAction::make('create_thread_plug_gauge')
                    ->label('Thread Plug Gauge')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => GaugeCalibrationResource::getUrl('create', ['type' => 'ThreadPlugGauge'])),

                Actions\CreateAction::make('create_serration_plug_gauge')
                    ->label('Serration Plug Gauge')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => GaugeCalibrationResource::getUrl('create', ['type' => 'SerrationPlugGauge'])),
                
                Actions\CreateAction::make('create_thread_ring_gauge')
                    ->label('Thread Ring Gauge')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => GaugeCalibrationResource::getUrl('create', ['type' => 'ThreadRingGauge'])),

                Actions\CreateAction::make('create_serration_ring_gauge')
                    ->label('Serration Ring Gauge')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => GaugeCalibrationResource::getUrl('create', ['type' => 'SerrationRingGauge'])),

                Actions\CreateAction::make('create_thread_plug_gauge_fit_wear')
                    ->label('Plug Gauge (Fit & Wear)')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => GaugeCalibrationResource::getUrl('create', ['type' => 'ThreadPlugGaugeFitWear'])),

                
            ])
            ->label('New Gauge Calibration')
            ->icon('heroicon-o-plus')
            ->button()
            ->color('primary'),
        ];
    }
}
