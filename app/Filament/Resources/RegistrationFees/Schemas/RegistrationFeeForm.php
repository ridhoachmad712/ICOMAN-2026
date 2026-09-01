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
                        TextInput::make('currency')->default('IDR')->maxLength(8)->required(),
                        Select::make('audience')
                            ->label('Eligible participant')
                            ->options(['presenter' => 'Presenter / Pemakalah', 'participant' => 'Seminar participant / Peserta'])
                            ->required(),
                        TextInput::make('category.en')->label('Category (EN)')->required()->maxLength(255),
                        TextInput::make('category.id')->label('Category (ID)')->maxLength(255),
                        TextInput::make('price_early_bird')->label('Price (Early Bird)')->numeric()->prefix('Rp'),
                        DatePicker::make('early_bird_deadline')
                            ->label('Early-bird Deadline')
                            ->native(false)
                            ->helperText('Harga early-bird hanya berlaku sampai tanggal ini.'),
                        TextInput::make('price_regular')->label('Price (Regular)')->numeric()->prefix('Rp')->required(),
                        Textarea::make('notes.en')->label('Notes (EN)')->rows(2)->columnSpanFull(),
                        Textarea::make('notes.id')->label('Notes (ID)')->rows(2)->columnSpanFull(),
                        TextInput::make('order')->numeric()->default(0),
                    ]),
            ]);
    }
}
