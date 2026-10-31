<?php

namespace App\Filament\Resources\PresentationRooms\Pages;

use App\Filament\Resources\PresentationRooms\PresentationRoomResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePresentationRoom extends CreateRecord
{
    protected static string $resource = PresentationRoomResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['edition_id'] = currentEdition()?->id;

        return $data;
    }

    // Langsung ke halaman room supaya presenter bisa ditambahkan.
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
