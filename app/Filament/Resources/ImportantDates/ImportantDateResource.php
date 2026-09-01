<?php

namespace App\Filament\Resources\ImportantDates;

use App\Filament\Resources\ImportantDates\Pages\CreateImportantDate;
use App\Filament\Resources\ImportantDates\Pages\EditImportantDate;
use App\Filament\Resources\ImportantDates\Pages\ListImportantDates;
use App\Filament\Resources\ImportantDates\Schemas\ImportantDateForm;
use App\Filament\Resources\ImportantDates\Tables\ImportantDatesTable;
use App\Models\ImportantDate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ImportantDateResource extends Resource
{
    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['superadmin', 'content_admin']) ?? false;
    }

    protected static ?string $model = ImportantDate::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Konten';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ImportantDateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ImportantDatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportantDates::route('/'),
            'create' => CreateImportantDate::route('/create'),
            'edit' => EditImportantDate::route('/{record}/edit'),
        ];
    }
}
