<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Widgets\CalibrationStatsWidget;
use App\Filament\Widgets\MonthSelectorWidget;
use App\Filament\Widgets\DueThisMonthWidget;
use App\Filament\Widgets\CalibratedThisMonthWidget;
use App\Filament\Widgets\OverdueInstrumentsWidget;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'หน้าหลัก (Dashboard)';
    protected static ?string $title = 'Dashboard';
    
    public function getWidgets(): array
    {
        return [
            CalibrationStatsWidget::class,
            MonthSelectorWidget::class,
            DueThisMonthWidget::class,
            CalibratedThisMonthWidget::class,
            OverdueInstrumentsWidget::class,
        ];
    }
}

