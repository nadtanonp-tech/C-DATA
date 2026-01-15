<?php

namespace App\Filament\Resources\ToolTypeResource\Widgets;

use App\Models\ToolType;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ToolTypeStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // นับจำนวน Type ทั้งหมด
        $totalTypes = ToolType::count();
        
        // นับจำนวน Type ที่ถูกใช้งาน (มี instrument อย่างน้อย 1 ตัว)
        $usedTypes = ToolType::has('instruments')->count();
        
        // นับจำนวน Type ที่ยังไม่ถูกใช้งาน
        $unusedTypes = ToolType::doesntHave('instruments')->count();

        return [
            Stat::make('ประเภทเครื่องมือทั้งหมด', number_format($totalTypes))
                ->description('จำนวน Type ในระบบ')
                ->descriptionIcon('heroicon-m-tag')
                ->color('primary'),
            Stat::make('ถูกใช้งานแล้ว', number_format($usedTypes))
                ->description('Type ที่มี Instrument อย่างน้อย 1 ตัว')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('ยังไม่ถูกใช้งาน', number_format($unusedTypes))
                ->description('Type ที่ยังไม่มี Instrument')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}
