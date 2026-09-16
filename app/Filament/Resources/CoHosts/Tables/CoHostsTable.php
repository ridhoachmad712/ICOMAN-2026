<?php

namespace App\Filament\Resources\CoHosts\Tables;

use App\Models\CoHost;
use App\Services\CoHostApproval;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class CoHostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('institution_name')->label('Institusi')->searchable()->wrap(),
                TextColumn::make('institution_type')
                    ->label('Jenis')
                    ->formatStateUsing(fn (CoHost $record): string => $record->typeLabel() ?? '—')
                    ->toggleable(),
                TextColumn::make('author.name')->label('Penanggung jawab')->searchable()->toggleable(),
                TextColumn::make('author.email')->label('Email')->copyable()->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (CoHost $record): string => $record->statusLabel())
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('voucher.code')
                    ->label('Kode voucher')
                    ->fontFamily('mono')
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('partnership')
                    ->label('Kemitraan')
                    // Disetujui belum berarti berjalan: vouchernya baru menyala
                    // setelah biaya kemitraannya lunas.
                    ->state(fn (CoHost $record): string => match (true) {
                        ! $record->isApproved() => '—',
                        $record->isActive() => 'Aktif',
                        default => 'Menunggu pembayaran',
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Aktif' ? 'success' : 'gray'),
                TextColumn::make('created_at')->label('Diajukan')->dateTime('d M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(CoHost::STATUSES),
                SelectFilter::make('institution_type')->label('Jenis')->options(CoHost::TYPES),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Rincian')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (CoHost $record) => $record->institution_name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->schema(fn (CoHost $record) => [
                        Text::make(new HtmlString(static::details($record))),
                    ]),

                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (CoHost $record): bool => $record->isPending() && (auth()->user()?->isSuperadmin() ?? false))
                    ->requiresConfirmation()
                    ->modalHeading(fn (CoHost $record) => 'Setujui '.$record->institution_name.'?')
                    ->modalDescription('Voucher untuk '.CoHost::FREE_PAPERS.' paper gratis disiapkan dan invoice biaya kemitraan diterbitkan. Kode baru menyala setelah invoicenya lunas.')
                    ->action(function (CoHost $record): void {
                        try {
                            $approved = app(CoHostApproval::class)->approve($record, auth()->id());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Belum bisa disetujui')
                                ->body($e->validator->errors()->first())
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title($approved->institution_name.' disetujui.')
                            ->body('Kode '.$approved->voucher?->code.' sudah dikirim ke penanggung jawab beserta invoice kemitraannya.')
                            ->success()
                            ->persistent()
                            ->send();
                    }),

                ActionGroup::make([
                    Action::make('reject')
                        ->label('Tolak')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->visible(fn (CoHost $record): bool => $record->isPending() && (auth()->user()?->isSuperadmin() ?? false))
                        ->schema([
                            Textarea::make('reason')
                                ->label('Alasan penolakan')
                                ->helperText('Disimpan sebagai catatan panitia dan bisa disampaikan ke pemohon.')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (array $data, CoHost $record): void {
                            app(CoHostApproval::class)->reject($record, $data['reason'], auth()->id());

                            Notification::make()->title('Pengajuan ditolak.')->success()->send();
                        }),

                    DeleteAction::make(),
                ])->label('Lainnya'),
            ])
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->emptyStateHeading('Belum ada pengajuan co-host')
            ->emptyStateDescription('Pengajuan muncul di sini setelah institusi mengisi formulir di website.');
    }

    private static function details(CoHost $record): string
    {
        $rows = [
            'Jenis institusi' => $record->typeLabel() ?? '—',
            'Negara' => $record->country ? (countryOptions()[$record->country] ?? $record->country) : '—',
            'Situs' => $record->website ?: '—',
            'Penanggung jawab' => $record->author?->name.' ('.($record->pic_position ?: '—').')',
            'Email' => $record->author?->email ?? '—',
            'Telepon' => $record->author?->phone ?: '—',
            'Status' => $record->statusLabel(),
        ];

        if ($record->rejection_reason) {
            $rows['Alasan penolakan'] = $record->rejection_reason;
        }

        if ($record->voucher) {
            $rows['Kode voucher'] = $record->voucher->code.' — '.$record->voucher->remainingSlots().' dari '.$record->voucher->quota.' slot tersisa';
        }

        if ($registration = $record->registration()) {
            $rows['Invoice kemitraan'] = 'IDR '.number_format((float) $registration->amount, 0, ',', '.').' — '.$registration->status;
        }

        $html = '<dl style="line-height:1.9">';
        foreach ($rows as $label => $value) {
            $html .= '<div><strong>'.e($label).':</strong> '.e($value).'</div>';
        }

        return $html.'</dl>';
    }
}
