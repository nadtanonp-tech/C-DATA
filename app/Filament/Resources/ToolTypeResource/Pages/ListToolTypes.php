<?php

namespace App\Filament\Resources\ToolTypeResource\Pages;

use App\Filament\Resources\ToolTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListToolTypes extends ListRecords
{
    protected static string $resource = ToolTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
