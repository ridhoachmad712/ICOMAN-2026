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
 * Pemantauan semua akun peserta/pemakalah (guard `author`), termasuk yang
 * belum memiliki submission. Aksi pengelolaan akun tetap hanya untuk superadmin.
 */
class AuthorResource extends Resource
{
    protected static ?string $model = Author::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Submission';

    protected static ?string $slug = 'author-accounts';

    protected static ?int $navigationSort = 0;

    public static function getNavigationLabel(): string
    {
        return 'Progres Peserta';
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

    /** Data peserta hanya untuk panitia yang menangani submission. */
    public static function canAccess(): bool
    {
        return auth()->user()?->managesSubmissions() ?? false;
    }

    public static function canCreate(): bool
    {
        return false; // akun dibuat sendiri oleh peserta lewat portal
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    /**
     * Hapus permanen. Tabel `authors` tidak memakai soft delete, jadi baris
     * benar-benar hilang dan alamat emailnya bebas dipakai mendaftar lagi.
     */
    public static function canDelete($record): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuthors::route('/'),
        ];
    }
}
