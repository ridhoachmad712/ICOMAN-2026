<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'superadmin' => 'danger',
                        'content_admin' => 'warning',
                        'reviewer' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('app_authentication_secret')
                    ->label('2FA')
                    ->formatStateUsing(fn ($state) => filled($state) ? 'On' : 'Off')
                    ->badge()
                    ->color(fn ($state) => filled($state) ? 'success' : 'gray'),
                TextColumn::make('created_at')->dateTime('d M Y')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('roles')->relationship('roles', 'name')->label('Role'),
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
