<?php

namespace App\Filament\Pages\Finance\Widgets;

use App\Filament\Resources\Registrations\RegistrationResource;
use App\Models\Registration;
use App\Support\FinanceSummary;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Daftar piutang: siapa yang masih berutang, berapa, dan sampai kapan.
 *
 * Inilah yang selama ini disalin panitia ke Excel, karena tidak ada satu pun
 * halaman yang menjawabnya. Yang lunas tidak muncul di sini — daftar ini
 * pekerjaan, bukan arsip.
 */
class OutstandingInvoices extends BaseWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Piutang')
            ->description('Invoice yang uangnya masih ditunggu. Yang dibebaskan voucher dan yang sudah gagal tidak dihitung sebagai piutang.')
            ->query(fn () => app(FinanceSummary::class)->stillOwing()->with(['author', 'registrationFee']))
            ->defaultSort('id')
            ->emptyStateHeading('Tidak ada piutang')
            ->emptyStateDescription('Semua invoice yang masih berjalan sudah lunas.')
            ->recordUrl(fn (Registration $record): string => RegistrationResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('id')
                    ->label('Invoice')
                    ->formatStateUsing(fn ($state): string => '#'.str_pad((string) $state, 5, '0', STR_PAD_LEFT)),

                TextColumn::make('author.name')
                    ->label('Peserta')
                    ->searchable()
                    ->description(fn (Registration $record): ?string => $record->author?->email),

                TextColumn::make('amount')
                    ->label('Tagihan')
                    ->formatStateUsing(fn ($state): string => rupiah($state)),

                TextColumn::make('paid')
                    ->label('Sudah dibayar')
                    ->state(fn (Registration $record): string => rupiah($record->paidAmount()))
                    ->color(fn (Registration $record): string => $record->paidAmount() > 0 ? 'success' : 'gray'),

                TextColumn::make('outstanding')
                    ->label('Sisa')
                    ->state(fn (Registration $record): string => rupiah($record->outstandingAmount()))
                    ->weight('bold'),

                TextColumn::make('due')
                    ->label('Tenggat pelunasan')
                    ->badge()
                    ->state(fn (Registration $record): string => self::due($record))
                    ->color(fn (Registration $record): string => $record->isInstallmentOverdue() ? 'danger' : 'gray'),
            ]);
    }

    /**
     * Tenggat hanya berlaku bagi yang benar-benar sedang mencicil. Invoice yang
     * belum dibayar sama sekali tidak menunggak apa pun — ia belum mulai.
     */
    private static function due(Registration $record): string
    {
        if (! $record->installment_plan || ! $record->isPartiallyPaid()) {
            return 'Belum dibayar';
        }

        $due = $record->installmentDueAt();

        if (! $due) {
            return 'Tenggat belum diatur';
        }

        return ($record->isInstallmentOverdue() ? 'Lewat tenggat · ' : '').$due->format('d M Y');
    }
}
