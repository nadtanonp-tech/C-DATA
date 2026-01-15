<?php

namespace App\Filament\Resources\ToolTypeResource\Pages;

use App\Filament\Resources\ToolTypeResource;
use App\Filament\Resources\ToolTypeResource\Widgets\ToolTypeStatsWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListToolTypes extends ListRecords
{
    protected static string $resource = ToolTypeResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ToolTypeStatsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\CreateAction::make()
                    ->label('K-Gauge Type')
                    ->color('gray')
                    ->url(fn (): string => ToolTypeResource::getUrl('create', ['is_kgauge' => 1])),
                
                Actions\CreateAction::make('createSnapGauge')
                    ->label('Snap Gauge Type')
                    ->color('gray')
                    ->url(fn (): string => ToolTypeResource::getUrl('create', ['is_snap_gauge' => 1])),
                
                Actions\CreateAction::make('createPlugGauge')
                    ->label('Plug Gauge Type')
                    ->color('gray')
                    ->url(fn (): string => ToolTypeResource::getUrl('create', ['is_plug_gauge' => 1])),

                Actions\CreateAction::make('createThreadPlugGauge')
                    ->label('Thread Plug Gauge Type')
                    ->color('gray')
                    ->url(fn (): string => ToolTypeResource::getUrl('create', ['is_thread_plug_gauge' => 1])),

                Actions\CreateAction::make('createThreadRingGauge')
                    ->label('Thread Ring Gauge Type')
                    ->color('gray')
                    ->url(fn (): string => ToolTypeResource::getUrl('create', ['is_thread_ring_gauge' => 1])),
                
                Actions\CreateAction::make('createSerrationPlugGauge')
                    ->label('Serration Plug Gauge Type')
                    ->tooltip('Serration Plug Gauge Type')
                    ->color('gray')
                    ->url(fn (): string => ToolTypeResource::getUrl('create', ['is_serration_plug_gauge' => 1])),

                Actions\CreateAction::make('createSerrationRingGauge')
                    ->label('Serration Ring Gauge Type')
                    ->tooltip('Serration Ring Gauge Type')
                    ->color('gray')
                    ->url(fn (): string => ToolTypeResource::getUrl('create', ['is_serration_ring_gauge' => 1])),
                
                Actions\CreateAction::make('createThreadPlugGaugeForCheckingFitWear')
                    ->label('Thread Plug Gauge Checking Fit & Wear')
                    ->tooltip('Thread Plug Gauge For Checking Fit & Wear Type') // Tooltip ชื่อเต็ม
                    ->color('gray')
                    ->url(fn (): string => ToolTypeResource::getUrl('create', ['is_thread_plug_gauge_for_checking_fit_wear' => 1])),
                
                Actions\CreateAction::make('createSerrationPlugGaugeForCheckingFitWear')
                    ->label('Serration Plug Gauge Checking Fit & Wear')
                    ->tooltip('Serration Plug Gauge For Checking Fit & Wear Type') // Tooltip ชื่อเต็ม
                    ->color('gray')
                    ->url(fn (): string => ToolTypeResource::getUrl('create', ['is_serration_plug_gauge_for_checking_fit_wear' => 1])),
            ])
            ->label('Create New Gauge Type')
            ->color('primary')
            ->button(),

            // กลุ่มใหม่สำหรับ Instruments
            Actions\CreateAction::make('createInstrumentType')
                ->label('Create New Instruments Type') // ปุ่มเดียวรวมทุกอย่าง
                ->url(fn (): string => ToolTypeResource::getUrl('create', ['is_new_instruments_type' => 1])),
        ];
    }
}
