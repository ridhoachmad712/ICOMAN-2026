<?php

namespace App\Filament\Author\Resources\Registrations\Pages;

use App\Filament\Author\Resources\Registrations\RegistrationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRegistrations extends ListRecords
{
    protected static string $resource = RegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(app()->getLocale() === 'id' ? 'Buat Registrasi' : 'Create Registration')
                ->visible(RegistrationResource::canCreate()),
        ];
    }

    public function getTitle(): string
    {
        return app()->getLocale() === 'id' ? 'Registrasi & Pembayaran' : 'Registration & Payment';
    }

    public function getSubheading(): ?string
    {
        return app()->getLocale() === 'id'
            ? 'Lihat invoice dan status pembayaran. Registrasi presenter otomatis mencakup akses seminar.'
            : 'View invoices and payment status. Presenter registration automatically includes seminar access.';
    }
}
