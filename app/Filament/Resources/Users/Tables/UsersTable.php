<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
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
                // Hapus permanen. Sebelumnya aksi ini hanya ada di halaman edit
                // sehingga sulit ditemukan, dan akun yang dianggap "sudah
                // dihapus" sebenarnya masih menempati alamat emailnya.
                DeleteAction::make()
                    ->modalHeading(fn (User $record) => 'Hapus akun '.$record->name.'?')
                    ->modalDescription(fn (User $record) => 'Baris database akun ini dihapus permanen, sehingga alamat email '.$record->email.' bisa dipakai lagi. Penugasan review beserta nilai yang sudah diberikannya juga ikut terhapus.')
                    ->modalSubmitActionLabel('Hapus permanen'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
