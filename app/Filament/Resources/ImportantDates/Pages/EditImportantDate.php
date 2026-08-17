<?php

namespace App\Filament\Resources\ImportantDates\Pages;

use App\Filament\Concerns\ExpandsTranslationsOnFill;
use App\Filament\Resources\ImportantDates\ImportantDateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditImportantDate extends EditRecord
{
    use ExpandsTranslationsOnFill;

    protected static string $resource = ImportantDateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
