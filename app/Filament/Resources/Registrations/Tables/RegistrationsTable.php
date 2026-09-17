<?php

namespace App\Filament\Resources\Registrations\Tables;

use App\Models\Registration;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RegistrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('author.name')->label('Peserta')->searchable(),
                TextColumn::make('registrationFee.category')->label('Kategori')->toggleable(),
                TextColumn::make('amount')->money('IDR')->sortable(),
                TextColumn::make('payment_method')->badge()->formatStateUsing(fn ($s) => ucfirst($s)),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucwords(str_replace('_', ' ', $state)))
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'pending_verification' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                // Cicilan tidak mengubah status: registrasi tetap "pending" sampai
                // lunas, jadi keadaannya perlu kolomnya sendiri agar panitia tahu
                // mana yang sudah membayar sebagian dan mana yang menunggak.
                TextColumn::make('installment')
                    ->label('Cicilan')
                    ->state(fn (Registration $record): string => match (true) {
                        ! $record->installment_plan => '—',
                        $record->status === 'paid' => 'Lunas 2/2',
                        $record->isPartiallyPaid() => 'Cicilan 1/2 — sisa '.number_format($record->outstandingAmount(), 0, ',', '.'),
                        default => 'Belum ada yang dibayar',
                    })
                    ->badge()
                    ->color(fn (Registration $record): string => match (true) {
                        ! $record->installment_plan => 'gray',
                        $record->isInstallmentOverdue() => 'danger',
                        $record->status === 'paid' => 'success',
                        default => 'warning',
                    })
                    ->description(fn (Registration $record): ?string => $record->isInstallmentOverdue()
                        ? 'Lewat tenggat '.$record->installmentDueAt()?->format('d M Y')
                        : null),
                TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                Filter::make('installment_overdue')
                    ->label('Menunggak cicilan')
                    ->query(fn ($query) => $query
                        ->where('installment_plan', true)
                        ->where('status', '!=', 'paid')
                        ->whereHas('payments', fn ($payments) => $payments->where('status', 'success'))),
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'pending_verification' => 'Pending Verification',
                    'paid' => 'Paid',
                    'failed' => 'Failed',
                ]),
                SelectFilter::make('payment_method')->options(['manual' => 'Manual', 'gateway' => 'Gateway']),
                SelectFilter::make('edition_id')->relationship('edition', 'name')->label('Edition'),
            ])
            ->recordActions([
                EditAction::make()->label('Detail'),

                Action::make('viewProof')
                    ->label('Bukti')
                    ->icon('heroicon-o-photo')
                    ->color('gray')
                    ->url(fn (Registration $record) => $record->getFirstMediaUrl('payment_proof') ?: null)
                    ->openUrlInNewTab()
                    ->visible(fn (Registration $record) => (bool) $record->getFirstMediaUrl('payment_proof')),

                // Jaring pengaman: bila notifikasi BorderPay gagal masuk (webhook/queue
                // bermasalah) admin tetap bisa menandai invoice yang sudah dibayar.
                Action::make('verify')
                    ->label('Tandai Lunas')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Tandai invoice ini LUNAS secara manual? Gunakan hanya bila pembayaran sudah dipastikan diterima tetapi status belum berubah otomatis.')
                    ->visible(fn (Registration $record) => $record->status !== 'paid')
                    ->action(function (Registration $record): void {
                        // Yang dicatat adalah SISA tagihan, bukan totalnya.
                        // Invoice cicilan yang sudah menerima 200.000 lalu
                        // ditandai lunas dengan nominal penuh akan tercatat
                        // 550.000 untuk tagihan 350.000 — dan angka pemasukan
                        // ikut menggelembung tanpa ada uang tambahan.
                        $outstanding = $record->outstandingAmount();

                        $record->update(['status' => 'paid', 'paid_at' => now()]);

                        if ($outstanding > 0) {
                            $record->payments()->create([
                                'method' => 'manual',
                                'amount' => $outstanding,
                                'status' => 'success',
                                'recorded_by' => auth()->id(),
                            ]);
                        }

                        Notification::make()->title('Pembayaran diverifikasi (paid).')->success()->send();
                    }),

                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Registration $record) => ! in_array($record->status, ['paid', 'failed'], true))
                    ->action(function (Registration $record): void {
                        $record->update(['status' => 'failed']);
                        $record->payments()->create([
                            'method' => 'manual',
                            'amount' => $record->amount,
                            'status' => 'failed',
                            'recorded_by' => auth()->id(),
                        ]);

                        Notification::make()->title('Registrasi ditolak.')->warning()->send();
                    }),
            ]);
    }
}
