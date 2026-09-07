<?php

namespace App\Filament\Resources\Authors\Pages;

use App\Filament\Resources\Authors\AuthorResource;
use Filament\Resources\Pages\ListRecords;

class ListAuthors extends ListRecords
{
    protected static string $resource = AuthorResource::class;

    public function getTitle(): string
    {
        return 'Akun Author';
    }

    public function getSubheading(): ?string
    {
        return 'Daftar akun peserta dan pemakalah. Gunakan "Reset Password" bila peserta lupa password.';
    }
}
