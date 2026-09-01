<?php

namespace App\Filament\Resources\ReviewAssignments\Pages;

use App\Filament\Resources\ReviewAssignments\ReviewAssignmentResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListReviewAssignments extends ListRecords
{
    protected static string $resource = ReviewAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua')
                ->badge(ReviewAssignmentResource::getEloquentQuery()->count())
                ->badgeColor('gray'),

            'pending' => Tab::make('Perlu Ditinjau (Pending)')
                ->badge(ReviewAssignmentResource::getEloquentQuery()->where('status', 'pending')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'pending')),

            'completed' => Tab::make('Selesai Direview (Completed)')
                ->badge(ReviewAssignmentResource::getEloquentQuery()->where('status', 'completed')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'completed')),
        ];
    }
}
