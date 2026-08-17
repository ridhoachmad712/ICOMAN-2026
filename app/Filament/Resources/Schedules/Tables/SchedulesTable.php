<?php

namespace App\Filament\Resources\Schedules\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SchedulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('day_date')
            ->columns([
                TextColumn::make('day_date')->date()->sortable(),
                TextColumn::make('time_start')->time('H:i')->sortable(),
                TextColumn::make('time_end')->time('H:i'),
                TextColumn::make('title')->searchable(),
                TextColumn::make('speaker_name')->toggleable(),
                TextColumn::make('room')->toggleable(),
                TextColumn::make('session_type')->badge(),
            ])
            ->filters([
                SelectFilter::make('session_type')->options([
                    'plenary' => 'Plenary',
                    'parallel' => 'Parallel',
                    'break' => 'Break',
                    'registration' => 'Registration',
                    'other' => 'Other',
                ]),
                SelectFilter::make('edition_id')->relationship('edition', 'name')->label('Edition'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
