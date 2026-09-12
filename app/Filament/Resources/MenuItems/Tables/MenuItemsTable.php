<?php

namespace App\Filament\Resources\MenuItems\Tables;

use App\Models\MenuItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MenuItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->reorderable('order')
            ->columns([
                TextColumn::make('label')
                    ->label('Label')
                    // Anak ditandai agar susunannya terbaca dalam satu daftar.
                    ->formatStateUsing(fn (MenuItem $record): string => ($record->parent_id ? '— ' : '').$record->label)
                    ->searchable(),
                TextColumn::make('parent.label')->label('Induk')->placeholder('Menu utama')->toggleable(),
                TextColumn::make('type')
                    ->label('Tujuan')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'page' => 'Halaman sendiri',
                        'url' => 'Tautan bebas',
                        default => 'Halaman bawaan',
                    })
                    ->color('gray'),
                TextColumn::make('target')
                    ->label('Alamat')
                    ->state(fn (MenuItem $record): string => $record->resolveUrl() ?? '— tidak aktif —')
                    ->color(fn (MenuItem $record): string => $record->resolveUrl() ? 'gray' : 'danger')
                    ->limit(40)
                    ->wrap(),
                IconColumn::make('is_published')->label('Tampil')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Jenis')->options([
                    'route' => 'Halaman bawaan',
                    'page' => 'Halaman sendiri',
                    'url' => 'Tautan bebas',
                ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-bars-3')
            ->emptyStateHeading('Menu masih memakai susunan bawaan')
            ->emptyStateDescription('Selama daftar ini kosong, website menampilkan menu bawaan. Klik "Muat Menu Bawaan" untuk mulai menyusunnya sendiri.');
    }
}
