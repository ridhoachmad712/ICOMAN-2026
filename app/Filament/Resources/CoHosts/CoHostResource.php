<?php

namespace App\Filament\Resources\CoHosts;

use App\Filament\Resources\CoHosts\Pages\ListCoHosts;
use App\Filament\Resources\CoHosts\Tables\CoHostsTable;
use App\Models\CoHost;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Pengajuan institusi co-host. Read-only kecuali dua keputusan: setujui atau
 * tolak — keduanya berkonsekuensi uang, jadi dibatasi ke superadmin.
 */
class CoHostResource extends Resource
{
    protected static ?string $model = CoHost::class;

    protected static ?string $recordTitleAttribute = 'institution_name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Submission';

    protected static ?int $navigationSort = 7;

    public static function getNavigationLabel(): string
    {
        return 'Pengajuan Co-host';
    }

    public static function getModelLabel(): string
    {
        return 'pengajuan co-host';
    }

    public static function getPluralModelLabel(): string
    {
        return 'pengajuan co-host';
    }

    /** Jumlah yang menunggu tinjauan, supaya tidak ada pengajuan yang terlupa. */
    public static function getNavigationBadge(): ?string
    {
        $pending = CoHost::where('status', 'pending')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['superadmin', 'admin_registrasi']) ?? false;
    }

    public static function canCreate(): bool
    {
        return false; // pengajuan datang dari institusi lewat website
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    public static function table(Table $table): Table
    {
        return CoHostsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoHosts::route('/'),
        ];
    }
}
