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
        
        // นับจำนวนเครื่องมือที่ใช้งาน (สถานะ: ใช้งาน, Spare)
        $activeInstruments = Instrument::whereIn('status', ['ใช้งาน', 'Spare'])->count();
        
        // นับจำนวนเครื่องมือที่ยกเลิก (สถานะ: ยกเลิก, สูญหาย)
        $cancelledInstruments = Instrument::whereIn('status', ['ยกเลิก', 'สูญหาย'])->count();
        
        // นับจำนวนเครื่องมือที่ส่งซ่อม
        $repairingInstruments = Instrument::where('status', 'ส่งซ่อม')->count();

        return [
            Stat::make('เครื่องมือทั้งหมด', number_format($totalInstruments))
                ->description('จำนวนเครื่องมือในระบบ')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('primary'),
            Stat::make('ใช้งานได้', number_format($activeInstruments))
                ->description('สถานะ: ใช้งาน, Spare')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('ยกเลิก/สูญหาย', number_format($cancelledInstruments))
                ->description('สถานะ: ยกเลิก, สูญหาย')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
            Stat::make('ส่งซ่อม', number_format($repairingInstruments))
                ->description('สถานะ: ส่งซ่อม')
                ->descriptionIcon('heroicon-m-cog-6-tooth')
                ->color('warning'),
        ];
    }
}
