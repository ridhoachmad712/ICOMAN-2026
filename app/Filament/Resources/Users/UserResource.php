<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Users & Roles';

    protected static ?string $recordTitleAttribute = 'name';

    /** Manajemen user hanya untuk superadmin. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    /**
     * Hapus permanen (tabel `users` tidak memakai soft delete). Dua pagar:
     * akun sendiri tidak boleh dihapus (bisa mengunci diri sendiri), dan
     * superadmin terakhir harus tetap ada agar panel tidak kehilangan pemilik.
     */
    public static function canDelete($record): bool
    {
        $actor = auth()->user();

        if (! $actor?->isSuperadmin() || $actor->is($record)) {
            return false;
        }

        if ($record->isSuperadmin() && static::superadminCount() <= 1) {
            return false;
        }

        return true;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    public static function superadminCount(): int
    {
        return User::role('superadmin')->count();
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
