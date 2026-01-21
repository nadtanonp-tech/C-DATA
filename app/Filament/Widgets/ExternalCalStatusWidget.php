<?php

namespace App\Filament\Widgets;

use App\Models\PurchasingRecord;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExternalCalStatusWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    
    protected static ?int $sort = 5;

    protected function getStats(): array
    {
        // นับจำนวนตามสถานะ
        $draft = PurchasingRecord::where('status', 'Draft')->count();
        $pending = PurchasingRecord::where('status', 'Pending')->count();
        $sent = PurchasingRecord::where('status', 'Sent')->count();
        $received = PurchasingRecord::where('status', 'Received')->count();
        $completed = PurchasingRecord::where('status', 'Completed')->count();
        
        // นับใกล้ครบกำหนด (7 วัน)
        $nearDue = PurchasingRecord::where('status', 'Sent')
            ->whereNotNull('expected_return_date')
            ->where('expected_return_date', '<=', now()->addDays(7))
            ->count();

        return [
            Stat::make('Draft', $draft)
                ->description('ร่าง/รอส่ง')
                ->descriptionIcon('heroicon-m-document')
                ->color('gray'),
                
            Stat::make('Sent', $sent)
                ->description('ส่งแล้ว')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color('info'),
                
            Stat::make('Received', $received)
                ->description('รับของแล้ว')
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color('success'),
                
            Stat::make('Completed', $completed)
                ->description('เสร็จสิ้น')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('primary'),
        ];
    }
    
    public static function canView(): bool
    {
        return true;
    }
}
