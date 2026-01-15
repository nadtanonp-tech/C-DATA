<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class CalibrationReport extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Calibration Report';
    protected static ?int $navigationSort = 2;
}
