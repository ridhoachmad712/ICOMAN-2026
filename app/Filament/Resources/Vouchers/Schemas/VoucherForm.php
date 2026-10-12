<?php

namespace App\Filament\Resources\Vouchers\Schemas;

use App\Models\Author;
use App\Models\Edition;
use App\Models\Voucher;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class VoucherForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pengaturan voucher')
                ->columns(2)
                ->schema([
                    TextInput::make('host_name')
                        ->label('Nama voucher / kampanye')
                        ->placeholder('Promo registrasi Oktober')
                        ->helperText('Nama ini tampil dalam rincian tagihan pengguna.')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('code')
                        ->label('Kode voucher')
                        ->helperText('Dibagikan kepada pengguna. Huruf besar/kecil tidak dibedakan.')
                        ->required()
                        ->maxLength(40)
                        ->unique(ignoreRecord: true)
                        ->default(fn () => 'ICOMAN-'.strtoupper(Str::random(7)))
                        ->disabled(fn (?Voucher $record): bool => ($record?->usedSlots() ?? 0) > 0)
                        ->dehydrated(fn (?Voucher $record): bool => ($record?->usedSlots() ?? 0) === 0)
                        ->dehydrateStateUsing(fn (?string $state): string => Voucher::normalizeCode($state)),

                    Select::make('type')
                        ->label('Jenis voucher')
                        ->options(fn (?Voucher $record): array => ($record?->type === 'cohost' ? ['cohost' => 'Co-host (kode lama)'] : []) + [
                            'discount' => 'Diskon registrasi',
                            'free_paper' => 'Paper gratis',
                        ])
                        ->default('discount')
                        ->required()
                        ->live()
                        ->disabled(fn (?Voucher $record): bool => $record?->type === 'cohost' || ($record?->usedSlots() ?? 0) > 0)
                        ->dehydrated(fn (?Voucher $record): bool => $record === null || ($record->type !== 'cohost' && $record->usedSlots() === 0))
                        ->helperText('Paper gratis membebaskan biaya registrasi dasar presenter.'),

                    Select::make('audience')
                        ->label('Berlaku untuk')
                        ->options([
                            'presenter' => 'Presenter',
                            'participant' => 'Peserta seminar',
                            'all' => 'Presenter dan peserta seminar',
                        ])
                        ->default('presenter')
                        ->required(fn (Get $get): bool => $get('type') === 'discount')
                        ->visible(fn (Get $get): bool => $get('type') === 'discount'),

                    Select::make('registrant_category')
                        ->label('Kategori peserta yang boleh memakai')
                        ->options(['all' => 'Semua kategori peserta'] + Author::CATEGORIES)
                        ->default('all')
                        ->required()
                        ->disabled(fn (?Voucher $record): bool => ($record?->usedSlots() ?? 0) > 0)
                        ->dehydrated(fn (?Voucher $record): bool => ($record?->usedSlots() ?? 0) === 0)
                        ->helperText('Berlaku untuk satu kategori saja, atau pilih semua kategori. Tidak dapat diubah setelah voucher dipakai.'),

                    Select::make('discount_kind')
                        ->label('Bentuk diskon')
                        ->options(['fixed' => 'Nominal (IDR)', 'percent' => 'Persentase (%)'])
                        ->default('fixed')
                        ->required(fn (Get $get): bool => $get('type') === 'discount')
                        ->live()
                        ->visible(fn (Get $get): bool => $get('type') === 'discount'),

                    TextInput::make('discount_value')
                        ->label('Nilai diskon')
                        ->numeric()
                        ->minValue(0.01)
                        ->maxValue(fn (Get $get): ?int => $get('discount_kind') === 'percent' ? 100 : null)
                        ->prefix(fn (Get $get): string => $get('discount_kind') === 'percent' ? '%' : 'IDR')
                        ->helperText('Diskon hanya memotong biaya registrasi dasar, maksimal sebesar biaya tersebut. Add-on jurnal tetap ditagih.')
                        ->required(fn (Get $get): bool => $get('type') === 'discount')
                        ->visible(fn (Get $get): bool => $get('type') === 'discount'),

                    Select::make('edition_id')
                        ->label('Edisi')
                        ->helperText('Voucher hanya berlaku pada edisi ini.')
                        ->options(fn () => Edition::orderByDesc('is_active')->orderByDesc('id')->pluck('name', 'id'))
                        ->default(fn () => currentEdition()?->id)
                        ->disabled(fn (?Voucher $record): bool => ($record?->usedSlots() ?? 0) > 0)
                        ->dehydrated(fn (?Voucher $record): bool => ($record?->usedSlots() ?? 0) === 0)
                        ->required(),
                ]),

            Section::make('Kuota & Masa Berlaku')
                ->columns(2)
                ->schema([
                    TextInput::make('quota')
                        ->label('Maksimal pengguna')
                        ->helperText('Satu akun memakai satu slot. Kuota tidak bisa lebih kecil dari jumlah yang sudah memakai kode.')
                        ->numeric()
                        ->minValue(fn (?Voucher $record): int => max(1, $record?->usedSlots() ?? 1))
                        ->default(1)
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
