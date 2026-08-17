<?php

namespace App\Filament\Resources\Sponsors\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SponsorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('order')
            ->defaultSort('order')
            ->columns([
                SpatieMediaLibraryImageColumn::make('logo')->collection('logo'),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('tier')->badge()->sortable(),
                TextColumn::make('website_url')->url(fn ($record) => $record->website_url)->toggleable(),
                TextColumn::make('order')->sortable(),
            ])
            ->filters([
                SelectFilter::make('tier')->options([
                    'platinum' => 'Platinum',
                    'gold' => 'Gold',
                    'silver' => 'Silver',
                    'partner' => 'Partner',
                    'media_partner' => 'Media Partner',
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
