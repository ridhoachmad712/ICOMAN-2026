<?php

namespace App\Filament\Resources\RegistrationFees\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RegistrationFeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Registration Fee')
                    ->columns(2)
                    ->schema([
                        Select::make('edition_id')
                            ->relationship('edition', 'name')
                            ->default(fn () => currentEdition()?->id)
                            ->required(),
                        Select::make('currency')->options(['IDR' => 'IDR', 'USD' => 'USD'])->default('IDR')->required()->live(),
                        TextInput::make('idr_exchange_rate')->label('Billing rate: IDR per USD')->numeric()->minValue(1)->visible(fn ($get) => $get('currency') === 'USD')->helperText('Required before USD checkout is enabled. The rate is frozen on each invoice.'),
                        Select::make('audience')
                            ->label('Eligible participant')
                            ->options(['presenter' => 'Presenter / Pemakalah', 'participant' => 'Seminar participant / Peserta'])
                            ->required(),
                        Select::make('registrant_category')
                            ->label('Registrant category')
                            ->options(\App\Models\Author::CATEGORIES)
                            ->default('general')
                            ->required()
                            ->helperText('Mahasiswa S1 vs Dosen/Umum. Tarif difilter berdasarkan pilihan author saat mendaftar.'),
                        TextInput::make('category.en')->label('Category (EN)')->required()->maxLength(255),
                        TextInput::make('category.id')->label('Category (ID)')->maxLength(255),
                        TextInput::make('price_regular')->label('Registration price')->numeric()->minValue(1)->required(),
                        Textarea::make('notes.en')->label('Notes (EN)')->rows(2)->columnSpanFull(),
                        Textarea::make('notes.id')->label('Notes (ID)')->rows(2)->columnSpanFull(),
                        TextInput::make('order')->numeric()->default(0),
                    ]),
            ]);
    }
}
