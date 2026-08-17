<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Page')
                    ->columns(2)
                    ->schema([
                        Select::make('edition_id')
                            ->relationship('edition', 'name')
                            ->helperText('Kosongkan untuk halaman global (lintas edition).'),
                        TextInput::make('slug')
                            ->maxLength(255)
                            ->helperText('Kosongkan untuk otomatis dari judul.'),
                        TextInput::make('title.en')->label('Title (EN)')->required()->maxLength(255),
                        TextInput::make('title.id')->label('Title (ID)')->maxLength(255),
                        RichEditor::make('content.en')->label('Content (EN)')->columnSpanFull(),
                        RichEditor::make('content.id')->label('Content (ID)')->columnSpanFull(),
                        Toggle::make('is_published')->label('Published'),
                    ]),

                Section::make('SEO Meta (opsional)')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('meta_title.en')->label('Meta Title (EN)')->maxLength(255),
                        TextInput::make('meta_title.id')->label('Meta Title (ID)')->maxLength(255),
                        Textarea::make('meta_description.en')->label('Meta Description (EN)')->rows(2),
                        Textarea::make('meta_description.id')->label('Meta Description (ID)')->rows(2),
                    ]),
            ]);
    }
}
