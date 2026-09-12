<?php

namespace App\Filament\Resources\PageSections\Tables;

use App\Filament\Resources\PageSections\Schemas\PageSectionForm;
use App\Models\PageSection;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PageSectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->reorderable('order')
            ->defaultGroup('target')
            ->columns([
                TextColumn::make('type')
                    ->label('Blok')
                    ->formatStateUsing(fn (PageSection $record): string => $record->typeLabel())
                    ->badge()
                    ->color('gray'),
                TextColumn::make('heading')
                    ->label('Judul')
                    ->placeholder('— tanpa judul —')
                    ->limit(45)
                    ->wrap(),
                TextColumn::make('target')
                    ->label('Halaman')
                    ->formatStateUsing(fn (string $state): string => PageSectionForm::targetOptions()[$state] ?? $state)
                    ->toggleable(),
                IconColumn::make('is_published')->label('Tampil')->boolean(),
            ])
            ->filters([
                SelectFilter::make('target')->label('Halaman')->options(fn () => PageSectionForm::targetOptions()),
                SelectFilter::make('type')->label('Jenis blok')->options(PageSection::TYPES),
            ])
            ->recordActions([
                EditAction::make(),
                ReplicateAction::make()->label('Duplikat')->excludeAttributes(['order']),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-squares-2x2')
            ->emptyStateHeading('Halaman masih memakai susunan bawaan')
            ->emptyStateDescription('Selama daftar ini kosong, beranda tampil dengan susunan bawaannya. Klik "Muat Susunan Bawaan" untuk mulai mengaturnya sendiri.');
    }
}
