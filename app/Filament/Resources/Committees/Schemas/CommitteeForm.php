<?php

namespace App\Filament\Resources\Committees\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CommitteeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Committee Member')
                    ->columns(2)
                    ->schema([
                        Select::make('edition_id')
                            ->relationship('edition', 'name')
                            ->default(fn () => currentEdition()?->id)
                            ->required(),
                        Select::make('category')
                            ->options([
                                'steering' => 'Steering Committee',
                                'organizing' => 'Organizing Committee',
                                'scientific' => 'Scientific Committee',
                                'reviewer' => 'Reviewer',
                            ])
                            ->required(),
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('affiliation')->maxLength(255),
                        TextInput::make('role_title.en')->label('Role Title (EN)')->maxLength(255),
                        TextInput::make('role_title.id')->label('Role Title (ID)')->maxLength(255),
                        \Filament\Forms\Components\Toggle::make('is_published')->label('Confirmed / Published')->default(false),
                        TextInput::make('order')->numeric()->default(0),
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
