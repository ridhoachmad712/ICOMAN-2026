<?php

namespace App\Filament\Resources\PresentationRooms;

use App\Filament\Resources\PresentationRooms\Pages\CreatePresentationRoom;
use App\Filament\Resources\PresentationRooms\Pages\EditPresentationRoom;
use App\Filament\Resources\PresentationRooms\Pages\ListPresentationRooms;
use App\Filament\Resources\PresentationRooms\RelationManagers\PresentationsRelationManager;
use App\Filament\Resources\PresentationRooms\Schemas\PresentationRoomForm;
use App\Filament\Resources\PresentationRooms\Tables\PresentationRoomsTable;
use App\Models\PresentationRoom;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Room presentasi paralel: dibuat dan diisi presenter oleh admin_room. */
class PresentationRoomResource extends Resource
{
    protected static ?string $model = PresentationRoom::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Conference';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function getNavigationLabel(): string
    {
        return 'Pembagian Room';
    }

    public static function getModelLabel(): string
    {
        return 'room';
    }

    public static function getPluralModelLabel(): string
    {
        return 'room presentasi';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->managesRooms() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('edition_id', currentEdition()?->id ?? -1);
    }

    public static function form(Schema $schema): Schema
    {
        return PresentationRoomForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PresentationRoomsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PresentationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPresentationRooms::route('/'),
            'create' => CreatePresentationRoom::route('/create'),
            'edit' => EditPresentationRoom::route('/{record}/edit'),
        ];
    }
}
