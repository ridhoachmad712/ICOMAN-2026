<?php

namespace App\Filament\Resources\Downloads\Pages;

use App\Filament\Concerns\ExpandsTranslationsOnFill;
use App\Filament\Resources\Downloads\DownloadResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDownload extends EditRecord
{
    use ExpandsTranslationsOnFill;

    protected static string $resource = DownloadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
