<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class MonthlyReport extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Monthly Report';
}
