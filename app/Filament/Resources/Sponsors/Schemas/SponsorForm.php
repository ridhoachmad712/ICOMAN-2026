<?php

namespace App\Filament\Resources\Sponsors\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SponsorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sponsor / Partner')
                    ->columns(2)
                    ->schema([
                        Select::make('edition_id')
                            ->relationship('edition', 'name')
                            ->default(fn () => currentEdition()?->id)
                            ->required(),
                        Select::make('tier')
                            ->options([
                                'platinum' => 'Platinum',
                                'gold' => 'Gold',
                                'silver' => 'Silver',
                                'partner' => 'Partner',
                                'media_partner' => 'Media Partner',
                            ])
                            ->default('partner')
                            ->required(),
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('website_url')->url()->maxLength(255),
                        \Filament\Forms\Components\Toggle::make('is_published')->label('Confirmed / Published')->default(false),
                        TextInput::make('order')->numeric()->default(0),
                        SpatieMediaLibraryFileUpload::make('logo')
                            ->collection('logo')
                            ->image()
                            ->imageEditor()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
