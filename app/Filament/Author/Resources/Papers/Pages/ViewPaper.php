<?php

namespace App\Filament\Author\Resources\Papers\Pages;

use App\Filament\Author\Pages\AuthorDashboard;
use App\Filament\Author\Resources\Papers\PaperResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewPaper extends ViewRecord
{
    protected static string $resource = PaperResource::class;

    protected string $view = 'filament.author.resources.papers.pages.view-paper';

    public function getTitle(): string|Htmlable
    {
        // Kode resmi yang sama dengan email dan PDF, supaya author cukup
        // menyebut satu nomor saat menghubungi panitia.
        return 'Paper '.$this->record->submission_number;
    }

    public function getSubheading(): ?string
    {
        return app()->getLocale() === 'id'
            ? 'Lanjutkan penulisan atau pantau verifikasi reviewer.'
            : 'Continue writing or track reviewer verification.';
    }

    /** Resource ini tidak punya halaman index; breadcrumb mengarah ke Dashboard. */
    public function getBreadcrumbs(): array
    {
        return [
            AuthorDashboard::getUrl(panel: 'author') => 'Dashboard',
            $this->getTitle(),
        ];
    }
}
