<?php

namespace App\Filament\Resources\PresentationResults\Pages;

use App\Filament\Resources\PresentationResults\PresentationResultResource;
use App\Models\RoomPresentation;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPresentationResults extends ListRecords
{
    protected static string $resource = PresentationResultResource::class;

    public function getTitle(): string
    {
        return 'Rekap & Pemenang';
    }

    public function getSubheading(): ?string
    {
        return 'Nilai moderator (rata-rata kriteria, skala 0–100) hanya acuan. Admin room menetapkan '
            .RoomPresentation::WINNERS.' juara Best Paper dan '.RoomPresentation::WINNERS.' juara Best Presenter dari seluruh room.';
    }

    public function getTabs(): array
    {
        $attended = fn (Builder $query) => $query->whereIn('attendance', RoomPresentation::ATTENDED);

        return [
            'all' => Tab::make('Semua')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->orderBy(fn ($room) => $room->select('order')->from('presentation_rooms')->whereColumn('presentation_rooms.id', 'room_presentations.presentation_room_id'))
                    ->orderBy('order')),
            'best_paper' => Tab::make('Peringkat Best Paper')
                ->icon('heroicon-o-document-text')
                ->modifyQueryUsing(fn (Builder $query) => $attended($query)->orderByDesc('paper_score')),
            'best_presenter' => Tab::make('Peringkat Best Presenter')
                ->icon('heroicon-o-microphone')
                ->modifyQueryUsing(fn (Builder $query) => $attended($query)->orderByDesc('presenter_score')),
            'winners' => Tab::make('Pemenang')
                ->icon('heroicon-o-trophy')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where(fn (Builder $query) => $query->whereNotNull('best_paper_rank')->orWhereNotNull('best_presenter_rank'))
                    ->orderByRaw('COALESCE(best_paper_rank, 99), COALESCE(best_presenter_rank, 99)')),
            'absent' => Tab::make('Tidak hadir')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('attendance', 'absent')),
        ];
    }
}
