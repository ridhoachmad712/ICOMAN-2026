<?php

namespace App\Filament\Resources\Registrations\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class RegistrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Registrasi')
                    ->columns(2)
                    ->schema([
                        Placeholder::make('peserta')->content(fn ($record) => $record?->author?->name ?? '—'),
                        Placeholder::make('email')->content(fn ($record) => $record?->author?->email ?? '—'),
                        Placeholder::make('kategori')->content(fn ($record) => $record?->registrationFee?->category ?? '—'),
                        Placeholder::make('voucher_code')->label('Voucher')->content(fn ($record) => $record?->voucher?->code ?? '—'),
                        TextInput::make('discount_amount')->label('Potongan voucher')->prefix('IDR')->disabled(),
                        TextInput::make('amount')->prefix('IDR')->disabled(),
                        TextInput::make('payment_method')->disabled(),
                        TextInput::make('status')->disabled(),
                        Placeholder::make('paid_at')->label('Dibayar')->content(fn ($record) => $record?->paid_at?->format('d M Y H:i') ?? '—'),
                        Placeholder::make('bukti')
                            ->label('Bukti Transfer')
                            ->content(function ($record): HtmlString {
                                return new HtmlString($record?->hasMedia('payment_proof')
                                    ? '<a href="'.e(route('admin.registrations.payment-proof', $record)).'" target="_blank" rel="noopener" class="text-primary-600 underline">Lihat bukti</a>'
                                    : '—');
                            }),
                    ]),
            ]);
    }
}
