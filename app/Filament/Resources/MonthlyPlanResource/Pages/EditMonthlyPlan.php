<?php

namespace App\Filament\Resources\MonthlyPlanResource\Pages;

use App\Filament\Resources\MonthlyPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMonthlyPlan extends EditRecord
{
    protected static string $resource = MonthlyPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
