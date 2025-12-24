<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'หน้าหลัก (Dashboard)';
    protected static ?string $title = 'Dashboard';
    
    public function getWidgets(): array
    {
        return [
        ];
    }
}
