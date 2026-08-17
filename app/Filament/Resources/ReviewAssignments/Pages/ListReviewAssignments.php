<?php

namespace App\Filament\Resources\ReviewAssignments\Pages;

use App\Filament\Resources\ReviewAssignments\ReviewAssignmentResource;
use Filament\Resources\Pages\ListRecords;

class ListReviewAssignments extends ListRecords
{
    protected static string $resource = ReviewAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
