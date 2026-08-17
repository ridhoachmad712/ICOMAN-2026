<?php

namespace App\Filament\Resources\ReviewAssignments\Pages;

use App\Filament\Resources\ReviewAssignments\ReviewAssignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReviewAssignment extends EditRecord
{
    protected static string $resource = ReviewAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
