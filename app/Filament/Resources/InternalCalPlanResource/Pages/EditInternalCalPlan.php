<?php

namespace App\Filament\Resources\InternalCalPlanResource\Pages;

use App\Filament\Resources\InternalCalPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInternalCalPlan extends EditRecord
{
    protected static string $resource = InternalCalPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
