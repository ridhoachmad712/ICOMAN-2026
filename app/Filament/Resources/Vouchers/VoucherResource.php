<?php

namespace App\Filament\Resources\Vouchers;

use App\Filament\Resources\Vouchers\Pages\CreateVoucher;
use App\Filament\Resources\Vouchers\Pages\EditVoucher;
use App\Filament\Resources\Vouchers\Pages\ListVouchers;
use App\Filament\Resources\Vouchers\Schemas\VoucherForm;
use App\Filament\Resources\Vouchers\Tables\VouchersTable;
use App\Models\Voucher;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Voucher registrasi. Admin registrasi dapat melihat pemakaian, sedangkan
 * superadmin mengatur potongan dan kuotanya.
 */
class VoucherResource extends Resource
{
    protected static ?string $model = Voucher::class;

    protected static ?string $recordTitleAttribute = 'code';

    protected static string|\UnitEnum|null $navigationGroup = 'Submission';

    protected static ?int $navigationSort = 6;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    public static function getNavigationLabel(): string
    {
        return 'Voucher';
    }

    public static function getModelLabel(): string
    {
        return 'voucher';
    }

    public static function getPluralModelLabel(): string
    {
        return 'voucher';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['superadmin', 'admin_registrasi']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    public static function canDelete($record): bool
    {
        // Kode yang sudah pernah dipakai adalah catatan siapa mendapat jatah
        // gratis; nonaktifkan saja, jangan hapus jejaknya.
        return (auth()->user()?->isSuperadmin() ?? false) && $record->redemptions()->doesntExist();
    }

    public static function form(Schema $schema): Schema
    {
        return VoucherForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VouchersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVouchers::route('/'),
            'create' => CreateVoucher::route('/create'),
            'edit' => EditVoucher::route('/{record}/edit'),
        ];
    }
}
