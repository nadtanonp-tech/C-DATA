<?php

namespace App\Filament\Resources\ToolTypeResource\Pages;

use App\Filament\Resources\ToolTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateToolType extends CreateRecord
{
    protected static string $resource = ToolTypeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
