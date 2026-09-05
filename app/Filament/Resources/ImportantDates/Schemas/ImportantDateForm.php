<?php

namespace App\Filament\Resources\ImportantDates\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ImportantDateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Important Date')
                    ->columns(2)
                    ->schema([
                        Select::make('edition_id')
                            ->relationship('edition', 'name')
                            ->default(fn () => currentEdition()?->id)
                            ->required(),
                        Select::make('kind')->options(\App\Services\ConferenceDeadlines::KINDS)->label('Workflow stage')->helperText('Leave empty for an informational date.'),
                        DatePicker::make('date')->native(false),
                        \Filament\Forms\Components\DateTimePicker::make('closes_at')->label('Exact closing time (WITA / UTC+8)')->seconds(false)->helperText('If empty, a dated deadline closes at 23:59 WITA. Leave both dates empty for To be announced.'),
                        TextInput::make('label.en')->label('Label (EN)')->required()->maxLength(255),
                        TextInput::make('label.id')->label('Label (ID)')->maxLength(255),
                        Toggle::make('is_highlighted')->label('Highlighted'),
                        TextInput::make('order')->numeric()->default(0),
                    ]),
            ]);
    }
}
