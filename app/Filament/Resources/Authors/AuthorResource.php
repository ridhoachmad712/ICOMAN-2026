<?php

namespace App\Filament\Resources\Authors;

use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Resources\Authors\Tables\AuthorsTable;
use App\Models\Author;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Daftar akun peserta/pemakalah (guard `author`). Read-only, kecuali satu aksi:
 * reset password — portal author tidak lagi punya reset mandiri, jadi bantuan
 * lupa password ditangani dari sini.
 */
class AuthorResource extends Resource
{
    protected static ?string $model = Author::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Peserta & Pembayaran';

    protected static ?string $slug = 'author-accounts';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return 'Akun Author';
    }

    public static function getModelLabel(): string
    {
        return 'akun author';
    }

    public static function getPluralModelLabel(): string
    {
        return 'akun author';
    }

    public static function table(Table $table): Table
    {
        return AuthorsTable::configure($table);
    }

    /** Data peserta bersifat pribadi; batasi ke superadmin. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false; // akun dibuat sendiri oleh peserta lewat portal
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuthors::route('/'),
        ];
    }
}
