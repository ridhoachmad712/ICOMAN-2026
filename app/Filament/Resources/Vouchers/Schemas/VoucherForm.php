<?php

namespace App\Filament\Resources\Vouchers\Schemas;

use App\Models\Edition;
use App\Models\Voucher;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class VoucherForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kode & Institusi')
                ->columns(2)
                ->schema([
                    TextInput::make('host_name')
                        ->label('Nama co-host')
                        ->placeholder('Universitas Mitra')
                        ->helperText('Muncul di invoice author saat voucher dipakai.')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('code')
                        ->label('Kode voucher')
                        ->helperText('Dibagikan co-host ke penulisnya. Huruf besar/kecil tidak dibedakan saat ditukar.')
                        ->required()
                        ->maxLength(40)
                        ->unique(ignoreRecord: true)
                        ->default(fn () => 'COHOST-'.strtoupper(Str::random(6)))
                        ->dehydrateStateUsing(fn (?string $state): string => Voucher::normalizeCode($state)),

                    Select::make('edition_id')
                        ->label('Edisi')
                        ->helperText('Voucher hanya berlaku pada edisi ini.')
                        ->options(fn () => Edition::orderByDesc('is_active')->orderByDesc('id')->pluck('name', 'id'))
                        ->default(fn () => currentEdition()?->id)
                        ->required(),
                ]),

            Section::make('Kuota & Masa Berlaku')
                ->columns(2)
                ->schema([
                    TextInput::make('quota')
                        ->label('Jumlah paper gratis')
                        ->helperText('Berapa kali kode ini boleh dipakai. Tidak bisa diturunkan di bawah slot yang sudah terpakai.')
                        ->numeric()
                        ->minValue(fn (?Voucher $record): int => max(1, $record?->usedSlots() ?? 1))
                        ->default(4)
                        ->required(),

                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->helperText('Nonaktifkan bila kode bocor. Slot yang sudah terpakai tidak terpengaruh.')
                        ->default(true)
                        ->inline(false),

                    DateTimePicker::make('expires_at')
                        ->label('Kedaluwarsa')
                        ->helperText('Kosongkan bila berlaku sampai edisi berakhir.')
                        ->seconds(false),
                ]),
        ]);
    }
}
