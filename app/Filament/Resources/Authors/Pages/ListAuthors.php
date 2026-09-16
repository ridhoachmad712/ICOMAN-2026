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
        return 'Akun Author';
    }

    public function getSubheading(): ?string
    {
        return 'Akun dibuat sendiri oleh peserta lewat portal. Panitia hanya bisa mereset password dan menghapus akun.';
    }

    /** Tab mengikuti jalur partisipasi — pertanyaan pertama tentang sebuah akun. */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua')->badge(Author::count()),
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
