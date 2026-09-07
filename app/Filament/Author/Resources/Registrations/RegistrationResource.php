<?php

namespace App\Filament\Author\Resources\Registrations;

use App\Filament\Author\Resources\Registrations\Pages\ViewRegistration;
use App\Models\Registration;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Registrasi author bersifat READ-ONLY di portal: invoice dibuat otomatis oleh
 * RegistrationProvisioner (lihat route `author.registration.checkout`), jadi
 * resource ini hanya menyediakan satu halaman — detail invoice & pembayaran.
 */
class RegistrationResource extends Resource
{
    protected static ?string $model = Registration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $slug = 'registrations';

    // Seluruh alur author dijalankan dari Dashboard (satu tempat). Halaman ini
    // tetap bisa diakses via tautan dashboard, tapi tidak muncul sebagai menu.
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return app()->getLocale() === 'id' ? 'registrasi' : 'registration';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('author_id', Filament::auth()->id())
            ->with(['registrationFee', 'submission', 'payments']);
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

    /**
     * Resource ini sengaja tidak punya halaman index (semua alur lewat Dashboard).
     * Filament tetap butuh URL "index" untuk hal seperti tombol Cancel pada form,
     * jadi arahkan ke Dashboard - tanpa ini halaman create melempar LogicException.
     */
    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        return \App\Filament\Author\Pages\AuthorDashboard::getUrl($parameters, $isAbsolute, $panel ?? 'author');
    }

    public static function getPages(): array
    {
        return [
            'view' => ViewRegistration::route('/{record}'),
        ];
    }
}
