<?php

namespace App\Filament\Resources\ImportantDates\Pages;

use App\Filament\Resources\ImportantDates\ImportantDateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListImportantDates extends ListRecords
{
    protected static string $resource = ImportantDateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
