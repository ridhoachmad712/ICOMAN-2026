<?php

namespace App\Filament\Resources\News\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NewsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('News / Announcement')
                    ->columns(2)
                    ->schema([
                        Select::make('edition_id')
                            ->relationship('edition', 'name')
                            ->default(fn () => currentEdition()?->id),
                        TextInput::make('slug')
                            ->maxLength(255)
                            ->helperText('Kosongkan untuk otomatis dari judul.'),
                        TextInput::make('title.en')->label('Title (EN)')->required()->maxLength(255),
                        TextInput::make('title.id')->label('Title (ID)')->maxLength(255),
                        Textarea::make('excerpt.en')->label('Excerpt (EN)')->rows(2)->columnSpanFull(),
                        Textarea::make('excerpt.id')->label('Excerpt (ID)')->rows(2)->columnSpanFull(),
                        RichEditor::make('content.en')->label('Content (EN)')->columnSpanFull(),
                        RichEditor::make('content.id')->label('Content (ID)')->columnSpanFull(),
                    ]),

                Section::make('Publishing')
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('published_at')->native(false),
                        Toggle::make('is_published')->label('Published'),
                        SpatieMediaLibraryFileUpload::make('thumbnail')
                            ->collection('thumbnail')
                            ->image()
                            ->imageEditor()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
