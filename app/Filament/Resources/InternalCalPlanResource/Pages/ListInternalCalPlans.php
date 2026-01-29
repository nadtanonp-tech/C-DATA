<?php

namespace App\Filament\Resources\InternalCalPlanResource\Pages;

use App\Filament\Resources\InternalCalPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInternalCalPlans extends ListRecords
{
    protected static string $resource = InternalCalPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
