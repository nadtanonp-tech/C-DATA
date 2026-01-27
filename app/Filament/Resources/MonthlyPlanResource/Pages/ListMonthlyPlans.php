<?php

namespace App\Filament\Resources\MonthlyPlanResource\Pages;

use App\Filament\Resources\MonthlyPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMonthlyPlans extends ListRecords
{
    protected static string $resource = MonthlyPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('เพิ่มแผน'),
        ];
    }
}
