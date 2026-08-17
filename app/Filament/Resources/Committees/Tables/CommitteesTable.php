<?php

namespace App\Filament\Resources\Committees\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CommitteesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('order')
            ->defaultSort('order')
            ->columns([
                SpatieMediaLibraryImageColumn::make('photo')->collection('photo')->circular(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('category')->badge()->sortable(),
                TextColumn::make('affiliation')->toggleable()->searchable(),
                TextColumn::make('order')->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')->options([
                    'steering' => 'Steering Committee',
                    'organizing' => 'Organizing Committee',
                    'scientific' => 'Scientific Committee',
                    'reviewer' => 'Reviewer',
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
