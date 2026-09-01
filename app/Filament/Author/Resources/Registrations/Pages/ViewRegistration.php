<?php

namespace App\Filament\Author\Resources\Registrations\Pages;

use App\Filament\Author\Resources\Registrations\RegistrationResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewRegistration extends ViewRecord
{
    protected static string $resource = RegistrationResource::class;

    protected string $view = 'filament.author.resources.registrations.pages.view-registration';

    public function getTitle(): string|Htmlable
    {
        return 'Invoice #'.str_pad((string) $this->record->id, 5, '0', STR_PAD_LEFT);
    }

    public function getSubheading(): ?string
    {
        return app()->getLocale() === 'id'
            ? 'Periksa tagihan dan selesaikan pembayaran dari halaman ini.'
            : 'Review the invoice and complete payment from this page.';
    }
}
