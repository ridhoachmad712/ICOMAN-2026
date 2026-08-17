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
                        TextInput::make('amount')->prefix('IDR')->disabled(),
                        TextInput::make('payment_method')->disabled(),
                        TextInput::make('status')->disabled(),
                        Placeholder::make('paid_at')->label('Dibayar')->content(fn ($record) => $record?->paid_at?->format('d M Y H:i') ?? '—'),
                        Placeholder::make('bukti')
                            ->label('Bukti Transfer')
                            ->content(function ($record): HtmlString {
                                $url = $record?->getFirstMediaUrl('payment_proof');

                                return new HtmlString($url
                                    ? '<a href="'.$url.'" target="_blank" class="text-primary-600 underline">Lihat bukti</a>'
                                    : '—');
                            }),
                    ]),
            ]);
    }
}
