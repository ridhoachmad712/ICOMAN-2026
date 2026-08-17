<?php

namespace App\Filament\Resources\Faqs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FaqForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('FAQ')
                    ->columns(2)
                    ->schema([
                        Select::make('edition_id')
                            ->relationship('edition', 'name')
                            ->default(fn () => currentEdition()?->id),
                        TextInput::make('order')->numeric()->default(0),
                        TextInput::make('question.en')->label('Question (EN)')->required()->columnSpanFull(),
                        TextInput::make('question.id')->label('Question (ID)')->columnSpanFull(),
                        Textarea::make('answer.en')->label('Answer (EN)')->rows(3)->required()->columnSpanFull(),
                        Textarea::make('answer.id')->label('Answer (ID)')->rows(3)->columnSpanFull(),
                    ]),
            ]);
    }
}
