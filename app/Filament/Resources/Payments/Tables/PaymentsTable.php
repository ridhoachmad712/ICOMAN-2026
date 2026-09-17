<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Filament\Resources\Registrations\RegistrationResource;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Belum ada transaksi')
            ->emptyStateDescription('Setiap percobaan pembayaran tercatat di sini, termasuk yang gagal.')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('registration.author'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('registration.author.name')
                    ->label('Peserta')
                    ->searchable()
                    ->description(fn (Payment $record): string => 'Invoice #'.str_pad((string) $record->registration_id, 5, '0', STR_PAD_LEFT)),

                TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR')
                    ->sortable()
                    // Jumlah yang tampil mengikuti penyaring, sehingga menyaring
                    // "berhasil + bulan ini" langsung memberi angka yang dicocokkan
                    // dengan mutasi bank.
                    ->summarize(Sum::make()->label('Jumlah')->money('IDR')),

                TextColumn::make('method')
                    ->label('Cara')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'gateway' => 'Gateway',
                        'manual' => 'Dicatat admin',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => $state === 'gateway' ? 'info' : 'warning')
                    // Baris manual berarti ada orang yang memutuskan uangnya
                    // sudah masuk. Itu yang perlu paling mudah ditemukan.
                    ->description(fn (Payment $record): ?string => $record->gateway_name),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'success' => 'Berhasil',
                        'failed' => 'Gagal',
                        'initiated' => 'Belum selesai',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('gateway_reference')
                    ->label('Referensi')
                    ->placeholder('—')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Nomor referensi disalin')
                    ->toggleable(),

                TextColumn::make('installment')
                    ->label('Keterangan')
                    ->state(fn (Payment $record): string => self::describe($record))
                    ->color('gray')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'success' => 'Berhasil',
                        'failed' => 'Gagal',
                        'initiated' => 'Belum selesai',
                    ]),

                SelectFilter::make('method')
                    ->label('Cara bayar')
                    ->options([
                        'gateway' => 'Gateway',
                        'manual' => 'Dicatat admin',
                    ]),

                Filter::make('period')
                    ->label('Rentang tanggal')
                    ->schema([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                Action::make('openInvoice')
                    ->label('Invoice')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Payment $record): ?string => $record->registration_id
                        ? RegistrationResource::getUrl('edit', ['record' => $record->registration_id])
                        : null),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    // Diekspor dari query tabel apa adanya, sehingga berkasnya
                    // berisi persis yang sedang dilihat — bukan seluruh isi
                    // tabel yang lalu harus disaring ulang di Excel.
                    ->action(fn (Table $table) => self::export($table)),
            ]);
    }

    /** Baris ini bagian dari cicilan, pelunasan, atau pembayaran sekali jalan. */
    private static function describe(Payment $record): string
    {
        $registration = $record->registration;

        if (! $registration) {
            return '—';
        }

        if (! $registration->installment_plan) {
            return 'Pembayaran penuh';
        }

        $order = $registration->payments()
            ->where('status', 'success')
            ->where('id', '<=', $record->id)
            ->count();

        return match (true) {
            $record->status !== 'success' => 'Cicilan',
            $order <= 1 => 'Cicilan pertama',
            default => 'Pelunasan',
        };
    }

    private static function export(Table $table): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = $table->getQuery()->clone()->with('registration.author');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Waktu', 'Invoice', 'Peserta', 'Nominal', 'Cara', 'Gateway', 'Status', 'Referensi', 'Keterangan']);

            $query->orderBy('id')->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $payment) {
                    fputcsv($out, [
                        $payment->created_at?->format('Y-m-d H:i'),
                        $payment->registration_id,
                        $payment->registration?->author?->name,
                        (float) $payment->amount,
                        $payment->method,
                        $payment->gateway_name,
                        $payment->status,
                        $payment->gateway_reference,
                        self::describe($payment),
                    ]);
                }
            });

            fclose($out);
        }, 'transaksi-'.Carbon::now()->format('Ymd-His').'.csv');
    }
}
