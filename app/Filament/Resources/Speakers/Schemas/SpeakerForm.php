<?php

namespace App\Filament\Resources\Speakers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SpeakerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Speaker')
                    ->columns(2)
                    ->schema([
                        Select::make('edition_id')
                            ->relationship('edition', 'name')
                            ->default(fn () => currentEdition()?->id)
                            ->required(),
                        Select::make('type')
                            ->options([
                                'keynote' => 'Keynote',
                                'invited' => 'Invited',
                                'plenary' => 'Plenary',
                            ])
                            ->required(),
                        TextInput::make('name')->label('Name (tanpa gelar)')->required()->maxLength(255),
                        TextInput::make('title_degree')->label('Title / Degree')->placeholder('Prof. Dr.')->helperText('Gelar saja, mis. "Prof. Dr." — jangan ulang di Name.')->maxLength(255),
                        TextInput::make('affiliation')->maxLength(255),
                        Select::make('country')
                            ->options(countries())
                            ->searchable()
                            ->native(false)
                            ->placeholder('Pilih negara'),
                        TextInput::make('order')->numeric()->default(0),
                    ]),

                Section::make('Topic & Bio (EN / ID)')
                    ->columns(2)
                    ->schema([
                        TextInput::make('topic.en')->label('Topic (EN)')->maxLength(255),
                        TextInput::make('topic.id')->label('Topic (ID)')->maxLength(255),
                        Textarea::make('bio.en')->label('Bio (EN)')->rows(4)->columnSpanFull(),
                        Textarea::make('bio.id')->label('Bio (ID)')->rows(4)->columnSpanFull(),
                    ]),

                Section::make('Photo')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('photo')
                            ->collection('photo')
                            ->image()
                            ->imageEditor(),
                    ]),
            ]);
    }
}
