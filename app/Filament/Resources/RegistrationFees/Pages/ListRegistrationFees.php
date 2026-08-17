<?php

namespace App\Filament\Resources\RegistrationFees\Pages;

use App\Filament\Resources\RegistrationFees\RegistrationFeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRegistrationFees extends ListRecords
{
    protected static string $resource = RegistrationFeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
