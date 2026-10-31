<?php

namespace App\Filament\Resources\PresentationResults;

use App\Filament\Resources\PresentationResults\Pages\ListPresentationResults;
use App\Filament\Resources\PresentationResults\Tables\PresentationResultsTable;
use App\Models\RoomPresentation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Rekap absensi dan nilai seluruh room, tempat admin_room menetapkan juara
 * 1–3 Best Paper dan Best Presenter. Nilai moderator hanya acuan.
 */
class PresentationResultResource extends Resource
{
    protected static ?string $model = RoomPresentation::class;

    protected static ?string $slug = 'rekap-presentasi';

    protected static string|\UnitEnum|null $navigationGroup = 'Conference';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    public static function getNavigationLabel(): string
    {
        return 'Rekap & Pemenang';
    }

    public static function getModelLabel(): string
    {
        return 'presentasi';
    }

    public static function getPluralModelLabel(): string
    {
        return 'rekap presentasi';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->managesRooms() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('room', fn (Builder $room) => $room->where('edition_id', currentEdition()?->id ?? -1));
    }

    public static function table(Table $table): Table
    {
        return PresentationResultsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPresentationResults::route('/'),
        ];
    }
}
