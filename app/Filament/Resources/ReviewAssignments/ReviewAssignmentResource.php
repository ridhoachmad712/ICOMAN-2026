<?php

namespace App\Filament\Resources\ReviewAssignments;

use App\Filament\Resources\ReviewAssignments\Pages\ListReviewAssignments;
use App\Filament\Resources\ReviewAssignments\Tables\ReviewAssignmentsTable;
use App\Models\ReviewAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ReviewAssignmentResource extends Resource
{
    protected static ?string $model = ReviewAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Submission & Review';

    protected static ?string $navigationLabel = 'My Reviews';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return ReviewAssignmentsTable::configure($table);
    }

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->hasRole('reviewer') && ! auth()->user()?->hasAnyRole(['superadmin', 'admin_registrasi'])
            ? 'My Reviews'
            : 'Penugasan Review';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['reviewer', 'admin_registrasi', 'superadmin']) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** Reviewer hanya melihat paper yang ditugaskan padanya; superadmin dan admin registrasi melihat semua. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['submission', 'reviewer', 'review']);

        $user = auth()->user();
        if ($user && $user->hasRole('reviewer') && ! $user->hasAnyRole(['superadmin', 'admin_registrasi'])) {
            $query->where('reviewer_id', $user->id);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviewAssignments::route('/'),
        ];
    }
}
