<?php

namespace App\Filament\Resources\Vouchers\Tables;

use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Services\VoucherRedeemer;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class VouchersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('host_name')->label('Co-host')->searchable()->wrap(),
                TextColumn::make('quota')
                    ->label('Terpakai')
                    // Sisa kuota adalah angka yang paling sering dicari panitia.
                    ->formatStateUsing(fn (Voucher $record): string => $record->usedSlots().' / '.$record->quota)
                    ->badge()
                    ->color(fn (Voucher $record): string => match (true) {
                        $record->remainingSlots() === 0 => 'danger',
                        $record->remainingSlots() <= 1 => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Voucher $record): string => match (true) {
                        ! $record->is_active => 'Nonaktif',
                        $record->isExpired() => 'Kedaluwarsa',
                        $record->remainingSlots() === 0 => 'Kuota habis',
                        default => 'Bisa dipakai',
                    })
                    ->color(fn (string $state): string => $state === 'Bisa dipakai' ? 'success' : 'gray'),
                TextColumn::make('edition.name')->label('Edisi')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('expires_at')->label('Kedaluwarsa')->dateTime('d M Y')->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Aktif'),
            ])
            ->recordActions([
                Action::make('slots')
                    ->label('Pemakai')
                    ->icon('heroicon-o-users')
                    ->color('gray')
                    ->modalHeading(fn (Voucher $record) => 'Pemakai voucher '.$record->code)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->schema(fn (Voucher $record) => [
                        Text::make(new HtmlString(static::slotList($record))),
                    ]),

                ActionGroup::make([
                    EditAction::make(),

                    // Melepas slot terakhir: untuk author yang menukar lalu menghilang.
                    Action::make('releaseLastSlot')
                        ->label('Lepas Slot Terakhir')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->visible(fn (Voucher $record): bool => (auth()->user()?->isSuperadmin() ?? false) && $record->usedSlots() > 0)
                        ->requiresConfirmation()
                        ->modalHeading('Lepas slot yang terakhir dipakai?')
                        ->modalDescription(fn (Voucher $record) => static::releaseWarning($record))
                        ->action(function (Voucher $record): void {
                            $redemption = $record->redemptions()->latest('redeemed_at')->first();

                            if (! $redemption) {
                                return;
                            }

                            $author = $redemption->author?->name ?? 'Author';
                            app(VoucherRedeemer::class)->release($redemption);

                            Notification::make()
                                ->title('Slot '.$author.' dilepas.')
                                ->body('Invoice yang bersangkutan kembali berstatus belum dibayar.')
                                ->success()
                                ->send();
                        }),

                    DeleteAction::make(),
                ])->label('Lainnya'),
            ])
            ->emptyStateIcon('heroicon-o-ticket')
            ->emptyStateHeading('Belum ada voucher co-host')
            ->emptyStateDescription('Buat kode untuk institusi mitra, lalu bagikan ke penulis mereka.');
    }

    private static function slotList(Voucher $voucher): string
    {
        $rows = $voucher->redemptions()->with(['author', 'registration'])->orderBy('redeemed_at')->get()
            ->map(fn (VoucherRedemption $redemption, int $index): string => sprintf(
                '<li>%d. <strong>%s</strong> — %s · %s</li>',
                $index + 1,
                e($redemption->author?->name ?? '—'),
                e($redemption->author?->email ?? '—'),
                e($redemption->redeemed_at->format('d M Y, H:i')),
            ))
            ->implode('');

        $remaining = '<p style="margin-top:.75rem">Sisa slot: <strong>'.$voucher->remainingSlots().'</strong> dari '.$voucher->quota.'.</p>';

        return $rows === ''
            ? '<p>Belum ada yang memakai kode ini.</p>'.$remaining
            : '<ol style="line-height:1.9">'.$rows.'</ol>'.$remaining;
    }

    private static function releaseWarning(Voucher $voucher): string
    {
        $redemption = $voucher->redemptions()->with('author')->latest('redeemed_at')->first();

        if (! $redemption) {
            return 'Belum ada slot yang terpakai.';
        }

        return 'Slot '.($redemption->author?->name ?? 'author').' dikembalikan ke kuota, dan invoice mereka kembali berstatus belum dibayar '
            .'dengan tagihan penuh. Beri tahu yang bersangkutan sebelum melakukan ini.';
    }
}
