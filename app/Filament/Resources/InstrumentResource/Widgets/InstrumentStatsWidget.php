<?php

namespace App\Filament\Resources\InstrumentResource\Widgets;

use App\Models\Instrument;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InstrumentStatsWidget extends BaseWidget
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('components.widget-spinner');
    }

    protected static ?string $pollingInterval = null;
    
    // 🚀 Lazy loading - ทำให้ widget โหลดแบบ async ไม่บล็อก navigation
    protected static bool $isLazy = true;
    
    protected function getStats(): array
    {
        $totalInstruments = Instrument::count();
        $activeInstruments = Instrument::where('status', 'ใช้งาน')->count();
        $spareInstruments = Instrument::where('status', 'Spare')->count();
        $lostInstruments = Instrument::where('status', 'สูญหาย')->count();
        $cancelledInstruments = Instrument::where('status', 'ยกเลิก')->count();
        $mastertypeInstruments = Instrument::where('equip_type', 'Master')->count();
        $workingtypeInstruments = Instrument::where('equip_type', 'Working')->count();
        $internaltypeInstruments = Instrument::where('cal_place', 'Internal')->count();
        $externaltypeInstruments = Instrument::where('cal_place', 'External')->count();
        $repairtypeInstruments = Instrument::where('status', 'ส่งซ่อม')->count();

        return [
            // 🔥 แถวที่ 1: เครื่องมือทั้งหมด, ใช้งาน, สำรอง, สูญหาย, ส่งซ่อม
            Stat::make('เครื่องมือทั้งหมด', number_format($totalInstruments))
                ->description('จำนวนเครื่องมือในระบบ')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('primary'),
                
            Stat::make('Active', number_format($activeInstruments))
                ->description('สถานะ: ใช้งาน')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
                
            Stat::make('Spare', number_format($spareInstruments))
                ->description('สถานะ: สํารอง')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('info'),

            Stat::make('Repair', number_format($repairtypeInstruments))
                ->description('สถานะ: ส่งซ่อม')
                ->descriptionIcon('heroicon-m-wrench')
                ->color('warning'),
                
            Stat::make('Inactive', number_format($lostInstruments))
                ->description('สถานะ: สูญหาย')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
            
            Stat::make('Cancelled', number_format($cancelledInstruments))
                ->description('สถานะ: ยกเลิก')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            Stat::make('Working', number_format($workingtypeInstruments))
                ->description('ประเภท: Working')
                ->descriptionIcon('heroicon-m-wrench')
                ->color('info'),
                
            Stat::make('Master', number_format($mastertypeInstruments))
                ->description('ประเภท: Master')
                ->descriptionIcon('heroicon-m-star')
                ->color('warning'),
                
            Stat::make('Internal', number_format($internaltypeInstruments))
                ->description('Cal: ภายใน')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('info'),
                
            Stat::make('External', number_format($externaltypeInstruments))
                ->description('Cal: ภายนอก')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('warning'),
            
           
        ];
    }
}