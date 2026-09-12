<?php

namespace App\Filament\Resources\PageSections;

use App\Filament\Resources\PageSections\Pages\CreatePageSection;
use App\Filament\Resources\PageSections\Pages\EditPageSection;
use App\Filament\Resources\PageSections\Pages\ListPageSections;
use App\Filament\Resources\PageSections\Schemas\PageSectionForm;
use App\Filament\Resources\PageSections\Tables\PageSectionsTable;
use App\Models\PageSection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** Penyusun halaman: blok-blok yang membentuk tampilan tiap halaman. */
class PageSectionResource extends Resource
{
    protected static ?string $model = PageSection::class;

    protected static ?string $recordTitleAttribute = 'heading';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Penyusun Halaman';
    }

    public static function getModelLabel(): string
    {
        return 'blok halaman';
    }

    public static function getPluralModelLabel(): string
    {
        return 'blok halaman';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['superadmin', 'content_admin']) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return PageSectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PageSectionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPageSections::route('/'),
            'create' => CreatePageSection::route('/create'),
            'edit' => EditPageSection::route('/{record}/edit'),
        ];
    }
}
