<?php

namespace App\Filament\Resources\Authors\Pages;

use App\Filament\Resources\Authors\AuthorResource;
use App\Models\Author;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAuthors extends ListRecords
{
    protected static string $resource = AuthorResource::class;

    public function getTitle(): string
    {
        return 'Progres Peserta';
    }

    public function getSubheading(): ?string
    {
        return 'Semua akun terlihat di sini, termasuk yang belum mengirim paper. Progres paper dan pembayaran mengikuti edisi aktif.';
    }

    /** Tab mengikuti jalur partisipasi — pertanyaan pertama tentang sebuah akun. */
    public function getTabs(): array
    {
        $editionId = currentEdition()?->id;
        $presentersWithoutPaper = fn (Builder $query): Builder => $editionId
            ? $query->where('participation_type', 'presenter')
                ->whereDoesntHave('submissions', fn (Builder $papers) => $papers->where('edition_id', $editionId))
            : $query->whereRaw('1 = 0');
        $presentersNeedingRevision = fn (Builder $query): Builder => $editionId
            ? $query->where('participation_type', 'presenter')
                ->whereHas('submissions', fn (Builder $papers) => $papers
                    ->where('edition_id', $editionId)
                    ->where('status', 'revision_required'))
            : $query->whereRaw('1 = 0');

        return [
            'all' => Tab::make('Semua')->badge(Author::count()),
            'not_started' => Tab::make('Belum mulai paper')
                ->badge($editionId ? $presentersWithoutPaper(Author::query())->count() : 0)
                ->badgeColor('warning')
                ->modifyQueryUsing($presentersWithoutPaper),
            'needs_revision' => Tab::make('Perlu revisi')
                ->badge($editionId ? $presentersNeedingRevision(Author::query())->count() : 0)
                ->badgeColor('warning')
                ->modifyQueryUsing($presentersNeedingRevision),
            'presenter' => Tab::make('Presenter')
                ->badge(Author::where('participation_type', 'presenter')->count())
                ->badgeColor('primary')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('participation_type', 'presenter')),
            'participant' => Tab::make('Peserta seminar')
                ->badge(Author::where('participation_type', 'participant')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('participation_type', 'participant')),
            'cohost' => Tab::make('Co-host')
                ->badge(Author::where('participation_type', 'cohost')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('participation_type', 'cohost')),
        ];
    }
}
