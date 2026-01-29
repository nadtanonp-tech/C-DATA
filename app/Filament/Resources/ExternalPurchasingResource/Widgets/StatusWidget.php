<?php

namespace App\Filament\Resources\ExternalPurchasingResource\Widgets;

use App\Models\PurchasingRecord;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatusWidget extends BaseWidget
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('components.widget-spinner');
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Draft', PurchasingRecord::where('status', 'Draft')->count())
                ->description('รายการร่าง')
                ->descriptionIcon('heroicon-m-document')
                ->color('gray'),

            Stat::make('Pending', PurchasingRecord::where('status', 'Pending')->count())
                ->description('รอดำเนินการ')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Sent', PurchasingRecord::where('status', 'Sent')->count())
                ->description('ส่งแล้ว')
                ->descriptionIcon('heroicon-m-truck')
                ->color('info'),

            Stat::make('Received', PurchasingRecord::where('status', 'Received')->count())
                ->description('รับของแล้ว')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Completed', PurchasingRecord::where('status', 'Completed')->count())
                ->description('เสร็จสิ้น')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('primary'),

            Stat::make('Cancelled', PurchasingRecord::where('status', 'Cancelled')->count())
                ->description('ยกเลิก')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}
