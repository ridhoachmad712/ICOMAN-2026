<?php

namespace App\Filament\Resources\Committees\Pages;

use App\Filament\Concerns\ExpandsTranslationsOnFill;
use App\Filament\Resources\Committees\CommitteeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCommittee extends EditRecord
{
    use ExpandsTranslationsOnFill;

    protected static string $resource = CommitteeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
