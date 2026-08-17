<?php

namespace App\Filament\Resources\RegistrationFees\Pages;

use App\Filament\Concerns\ExpandsTranslationsOnFill;
use App\Filament\Resources\RegistrationFees\RegistrationFeeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRegistrationFee extends EditRecord
{
    use ExpandsTranslationsOnFill;

    protected static string $resource = RegistrationFeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
