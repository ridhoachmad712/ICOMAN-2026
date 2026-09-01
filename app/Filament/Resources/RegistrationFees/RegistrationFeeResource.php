<?php

namespace App\Filament\Resources\RegistrationFees;

use App\Filament\Resources\RegistrationFees\Pages\CreateRegistrationFee;
use App\Filament\Resources\RegistrationFees\Pages\EditRegistrationFee;
use App\Filament\Resources\RegistrationFees\Pages\ListRegistrationFees;
use App\Filament\Resources\RegistrationFees\Schemas\RegistrationFeeForm;
use App\Filament\Resources\RegistrationFees\Tables\RegistrationFeesTable;
use App\Models\RegistrationFee;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RegistrationFeeResource extends Resource
{
    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['superadmin', 'admin_registrasi', 'content_admin']) ?? false;
    }

    protected static ?string $model = RegistrationFee::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Konten';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return RegistrationFeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RegistrationFeesTable::configure($table);
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
            'index' => ListRegistrationFees::route('/'),
            'create' => CreateRegistrationFee::route('/create'),
            'edit' => EditRegistrationFee::route('/{record}/edit'),
        ];
    }
}
