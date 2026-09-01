<?php

namespace App\Filament\Author\Resources\Papers\Pages;

use App\Filament\Author\Resources\Papers\PaperResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPapers extends ListRecords
{
    protected static string $resource = PaperResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(app()->getLocale() === 'id' ? 'Mulai Extended Abstract' : 'Start Extended Abstract'),
        ];
    }

    public function getTitle(): string
    {
        return app()->getLocale() === 'id' ? 'Extended Abstract Saya' : 'My Extended Abstract';
    }

    public function getSubheading(): ?string
    {
        $hasSubmission = PaperResource::getEloquentQuery()->exists();

        if ($hasSubmission) {
            return app()->getLocale() === 'id'
                ? 'Kuota satu paper Anda sudah digunakan. Lanjutkan penulisan atau pantau verifikasi reviewer di sini.'
                : 'Your one-paper quota has been used. Continue writing or track reviewer verification here.';
        }

        return app()->getLocale() === 'id'
            ? 'Setiap akun dapat mengirim satu extended abstract pada edisi ini.'
            : 'Each account may submit one extended abstract in this edition.';
    }
}
