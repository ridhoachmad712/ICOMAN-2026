<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Buku transaksi: setiap pembayaran, bukan setiap tagihan.
 *
 * Sebelum halaman ini, `payments` tidak punya pintu sama sekali di panel.
 * Yang terlihat hanya status invoice — lunas atau belum — sementara uang yang
 * membentuknya tidak bisa dilihat siapa pun: cicilan pertama masuk kapan,
 * lewat gateway atau dicatat manual, berapa percobaan yang gagal, nomor
 * referensi mana yang harus dicocokkan dengan mutasi bank.
 *
 * Halaman ini hanya membaca. Transaksi tidak disunting: yang sudah terjadi
 * adalah catatan, dan mengubah catatan bukan cara memperbaiki keadaan. Bila
 * ada yang keliru, perbaikannya lewat invoice di halaman Registrations, yang
 * meninggalkan barisnya sendiri di sini.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Submission';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function getNavigationLabel(): string
    {
        return 'Transaksi';
    }

    public static function getModelLabel(): string
    {
        return 'transaksi';
    }

    public static function getPluralModelLabel(): string
    {
        return 'transaksi';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->handlesMoney() ?? false;
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
        return PaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
        ];
    }
}
