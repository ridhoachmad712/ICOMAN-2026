<?php

namespace App\Filament\Resources\Certificates;

use App\Filament\Resources\Certificates\Pages\ListCertificates;
use App\Filament\Resources\Certificates\Tables\CertificatesTable;
use App\Models\Certificate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Sertifikat yang sudah terbit: dicari lewat nomor atau nama, dicabut, atau
 * diterbitkan ulang setelah koreksi. Sertifikat tidak dibuat atau disunting
 * satu per satu di sini; semuanya lahir dari tombol "Terbitkan sertifikat"
 * (lihat CertificateIssuer) supaya syarat dan penomorannya seragam.
 */
class CertificateResource extends Resource
{
    protected static ?string $model = Certificate::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Submission';

    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    public static function getNavigationLabel(): string
    {
        return 'Sertifikat';
    }

    public static function getModelLabel(): string
    {
        return 'sertifikat';
    }

    public static function getPluralModelLabel(): string
    {
        return 'sertifikat';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->managesSubmissions() ?? false;
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

    public static function table(Table $table): Table
    {
        return CertificatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCertificates::route('/'),
        ];
    }
}
