<?php

namespace App\Filament\Resources\RegistrationFees\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RegistrationFeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('order')
            ->defaultSort('order')
            ->columns([
                TextColumn::make('category')->searchable()->sortable(),
                TextColumn::make('price_early_bird')->money('IDR')->sortable(),
                TextColumn::make('price_regular')->money('IDR')->sortable(),
                TextColumn::make('currency')->toggleable(),
                TextColumn::make('order')->sortable(),
            ])
            ->filters([
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
