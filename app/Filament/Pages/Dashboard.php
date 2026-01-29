<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Widgets\CalibrationStatsWidget;
use App\Filament\Widgets\MonthSelectorWidget;
use App\Filament\Widgets\DueThisMonthWidget;
use App\Filament\Widgets\CalibratedThisMonthWidget;
use App\Filament\Widgets\OverdueInstrumentsWidget;
use App\Filament\Widgets\ExternalCalStatusWidget;
use App\Filament\Widgets\CalibrationCostChartWidget;
use App\Filament\Widgets\DueTypeChartWidget;

class Dashboard extends BaseDashboard
{
    public function getColumns(): int | string | array
    {
        return 2;
    }
    
    public function getWidgets(): array
    {
        return [
            CalibrationStatsWidget::class,
            MonthSelectorWidget::class,
            CalibrationCostChartWidget::class,
            DueTypeChartWidget::class,
            DueThisMonthWidget::class,
            CalibratedThisMonthWidget::class,
            OverdueInstrumentsWidget::class,
            
        ];
    }
}

