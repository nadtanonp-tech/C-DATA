<?php

namespace App\Filament\Resources\ExternalCalResultResource\Pages;

use App\Filament\Resources\ExternalCalResultResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExternalCalResults extends ListRecords
{
    protected static string $resource = ExternalCalResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('บันทึกผลสอบเทียบ'),
        ];
    }
}
