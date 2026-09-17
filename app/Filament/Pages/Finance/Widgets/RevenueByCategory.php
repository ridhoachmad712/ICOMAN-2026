<?php

namespace App\Filament\Pages\Finance\Widgets;

use App\Models\RegistrationFee;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Pemasukan per kategori peserta.
 *
 * Satu baris per tarif, bukan per invoice: itulah pengelompokan yang dipakai
 * panitia saat menyusun laporan ke fakultas. Angkanya dihitung dari baris
 * pembayaran masing-masing invoice, sehingga cicilan yang baru terbayar
 * separuh masuk sebagian ke "diterima" dan sisanya ke "piutang" — tidak
 * dibulatkan ke salah satunya.
 */
class RevenueByCategory extends BaseWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pemasukan per Kategori')
            ->description('Tarif yang belum pernah dipakai tetap ditampilkan, supaya kategori yang sepi juga terlihat.')
            ->query(
                RegistrationFee::query()
                    ->when(currentEdition(), fn ($q, $edition) => $q->where('edition_id', $edition->id))
                    ->with(['registrations.payments'])
            )
            ->paginated(false)
            ->emptyStateHeading('Belum ada kategori biaya')
            ->columns([
                TextColumn::make('category')
                    ->label('Kategori')
                    ->description(fn (RegistrationFee $record): string => ucfirst($record->audience).' · '.str_replace('_', ' ', $record->registrant_category)),

                TextColumn::make('price_regular')
                    ->label('Tarif')
                    // Tarif peserta internasional ditetapkan dalam USD, jadi
                    // ditampilkan apa adanya. Yang ditagihkan ke invoice tetap
                    // rupiah hasil konversi — karena itu dua kolom di kanan
                    // selalu IDR, dan kolom ini belum tentu.
                    ->formatStateUsing(fn ($state, RegistrationFee $record): string => $record->currency === 'IDR'
                        ? rupiah($state)
                        : $record->currency.' '.number_format((float) $state, 2, ',', '.')),

                TextColumn::make('invoices')
                    ->label('Invoice')
                    ->state(fn (RegistrationFee $record): int => $record->registrations->count()),

                TextColumn::make('received')
                    ->label('Diterima')
                    ->state(fn (RegistrationFee $record): string => rupiah(self::receivedFor($record)))
                    ->color('success'),

                TextColumn::make('outstanding')
                    ->label('Piutang')
                    ->state(fn (RegistrationFee $record): string => rupiah(self::outstandingFor($record)))
                    ->color(fn (RegistrationFee $record): string => self::outstandingFor($record) > 0 ? 'warning' : 'gray'),
            ]);
    }

    private static function receivedFor(RegistrationFee $fee): float
    {
        return (float) $fee->registrations
            ->sum(fn ($registration): float => (float) $registration->payments
                ->where('status', 'success')
                ->sum('amount'));
    }

    /**
     * Hanya invoice yang uangnya masih mungkin datang. Yang dibebaskan voucher
     * dan yang sudah gagal tidak ikut, sama seperti di ringkasan.
     */
    private static function outstandingFor(RegistrationFee $fee): float
    {
        return (float) $fee->registrations
            ->reject(fn ($registration): bool => $registration->payment_method === 'waived' || $registration->status === 'failed')
            ->sum(fn ($registration): float => $registration->outstandingAmount());
    }
}
