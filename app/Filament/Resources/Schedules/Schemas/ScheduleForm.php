<?php

namespace App\Filament\Resources\Schedules\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Schedule Session')
                    ->columns(2)
                    ->schema([
                        Select::make('edition_id')
                            ->relationship('edition', 'name')
                            ->default(fn () => currentEdition()?->id)
                            ->required(),
                        Select::make('session_type')
                            ->options([
                                'plenary' => 'Plenary',
                                'parallel' => 'Parallel',
                                'break' => 'Break',
                                'registration' => 'Registration',
                                'other' => 'Other',
                            ])
                            ->default('other')
                            ->required(),
                        DatePicker::make('day_date')->native(false),
                        TextInput::make('order')->numeric()->default(0),
                        TimePicker::make('time_start')->seconds(false),
                        TimePicker::make('time_end')->seconds(false),
                        TextInput::make('title.en')->label('Title (EN)')->required()->maxLength(255),
                        TextInput::make('title.id')->label('Title (ID)')->maxLength(255),
                        TextInput::make('speaker_name')->maxLength(255),
                        TextInput::make('room')->maxLength(255),
                    ]),
            ]);
    }
}
