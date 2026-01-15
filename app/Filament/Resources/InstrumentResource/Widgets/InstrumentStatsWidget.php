<?php

namespace App\Filament\Resources\InstrumentResource\Widgets;

use App\Models\Instrument;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InstrumentStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // นับจำนวนเครื่องมือทั้งหมด
        $totalInstruments = Instrument::count();
        
        // นับจำนวนเครื่องมือที่ใช้งาน (สถานะ: ใช้งาน)
        $activeInstruments = Instrument::where('status', 'ใช้งาน')->count();
        
        // นับจำนวนเครื่องมือสำรอง (สถานะ: Spare)
        $spareInstruments = Instrument::where('status', 'Spare')->count();
        
        // นับจำนวนเครื่องมือที่ยกเลิก (สถานะ: ยกเลิก)
        $cancelledInstruments = Instrument::where('status', 'ยกเลิก')->count();
        
        // นับจำนวนเครื่องมือที่สูญหาย (สถานะ: สูญหาย)
        $lostInstruments = Instrument::where('status', 'สูญหาย')->count();
        
        // นับจำนวนเครื่องมือที่ส่งซ่อม
        $repairingInstruments = Instrument::where('status', 'ส่งซ่อม')->count();

        return [
            Stat::make('เครื่องมือทั้งหมด', number_format($totalInstruments))
                ->description('จำนวนเครื่องมือในระบบ')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('primary'),
            Stat::make('ใช้งาน', number_format($activeInstruments))
                ->description('สถานะ: ใช้งาน')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('สำรอง (Spare)', number_format($spareInstruments))
                ->description('สถานะ: Spare')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('info'),
            Stat::make('ยกเลิก', number_format($cancelledInstruments))
                ->description('สถานะ: ยกเลิก')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
            Stat::make('สูญหาย', number_format($lostInstruments))
                ->description('สถานะ: สูญหาย')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
            Stat::make('ส่งซ่อม', number_format($repairingInstruments))
                ->description('สถานะ: ส่งซ่อม')
                ->descriptionIcon('heroicon-m-cog-6-tooth')
                ->color('warning'),
        ];
    }
}
