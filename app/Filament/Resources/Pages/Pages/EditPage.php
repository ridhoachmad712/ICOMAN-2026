<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Concerns\ExpandsTranslationsOnFill;
use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    use ExpandsTranslationsOnFill;

    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
