<?php

namespace App\Filament\Resources\MonthlyPlanResource\Pages;

use App\Filament\Resources\MonthlyPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMonthlyPlan extends CreateRecord
{
    protected static string $resource = MonthlyPlanResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
