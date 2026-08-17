<?php

namespace App\Filament\Resources\Downloads\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DownloadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Download / Template')
                    ->columns(2)
                    ->schema([
                        Select::make('edition_id')
                            ->relationship('edition', 'name')
                            ->default(fn () => currentEdition()?->id),
                        Select::make('category')
                            ->options([
                                'template' => 'Template',
                                'guideline' => 'Guideline',
                                'other' => 'Other',
                            ])
                            ->default('other')
                            ->required(),
                        TextInput::make('title.en')->label('Title (EN)')->required()->maxLength(255),
                        TextInput::make('title.id')->label('Title (ID)')->maxLength(255),
                        TextInput::make('order')->numeric()->default(0),
                        SpatieMediaLibraryFileUpload::make('file')
                            ->collection('file')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
