<?php

namespace App\Filament\Resources\ModeratorRooms;

use App\Filament\Resources\ModeratorRooms\Pages\ListModeratorRooms;
use App\Filament\Resources\PresentationRooms\ModerationActions;
use App\Filament\Resources\PresentationRooms\RelationManagers\PresentationsRelationManager;
use App\Models\RoomPresentation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Room Saya": presenter di room yang dipandu moderator yang sedang login.
 * Moderator tidak melihat room lain, data pembayaran, atau menu lain.
 */
class ModeratorRoomResource extends Resource
{
    protected static ?string $model = RoomPresentation::class;

    protected static ?string $slug = 'room-saya';

    protected static string|\UnitEnum|null $navigationGroup = 'Conference';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    public static function getNavigationLabel(): string
    {
        return 'Room Saya';
    }

    public static function getModelLabel(): string
    {
        return 'presenter';
    }

    public static function getPluralModelLabel(): string
    {
        return 'presenter';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isRoomModerator() ?? false;
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
        return parent::getEloquentQuery()->whereHas('room', fn (Builder $room) => $room
            ->where('edition_id', currentEdition()?->id ?? -1)
            ->where('moderator_id', auth()->id() ?? -1));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['submission.authors', 'submission.author', 'room']))
            ->defaultSort('order')
            ->groups([
                Group::make('room.name')
                    ->label('Room')
                    ->getDescriptionFromRecordUsing(fn (RoomPresentation $record): string => collect([
                        $record->room->scheduleLabel(),
                        $record->room->meeting_note,
                        $record->room->isLocked() ? 'Penilaian sudah dikirim (terkunci)' : null,
                    ])->filter()->implode(' · ')),
            ])
            ->defaultGroup('room.name')
            ->groupingSettingsHidden()
            ->paginated(false)
            ->columns(PresentationsRelationManager::columns())
            ->recordActions([
                ModerationActions::attendance(),
                ModerationActions::score(),
            ])
            ->emptyStateIcon('heroicon-o-presentation-chart-bar')
            ->emptyStateHeading('Belum ada room yang ditugaskan')
            ->emptyStateDescription('Admin room akan menunjuk Anda sebagai moderator. Daftar presenter muncul di sini setelahnya.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModeratorRooms::route('/'),
        ];
    }
}
