<?php

namespace App\Filament\Resources\Authors\Tables;

use App\Models\Author;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class AuthorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable()->wrap(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('participation_type')
                    ->label('Jalur')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state === 'presenter' ? 'Presenter' : 'Peserta seminar')
                    ->color(fn (?string $state) => $state === 'presenter' ? 'primary' : 'gray'),
                TextColumn::make('registrant_category')
                    ->label('Kategori')
                    ->formatStateUsing(fn (?string $state) => Author::CATEGORIES[$state] ?? '—'),
                TextColumn::make('affiliation')->label('Afiliasi')->limit(30)->toggleable()->placeholder('—'),
                TextColumn::make('country')->label('Negara')->toggleable(isToggledHiddenByDefault: true)->placeholder('—'),
                TextColumn::make('phone')->label('Telepon')->toggleable(isToggledHiddenByDefault: true)->placeholder('—'),
                TextColumn::make('created_at')->label('Terdaftar')->dateTime('d M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('participation_type')
                    ->label('Jalur')
                    ->options(['presenter' => 'Presenter', 'participant' => 'Peserta seminar']),
                SelectFilter::make('registrant_category')->label('Kategori')->options(Author::CATEGORIES),
            ])
            ->recordActions([
                // Portal author tidak punya reset mandiri, jadi ini satu-satunya
                // jalan memulihkan akun yang lupa password.
                Action::make('resetPassword')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->modalHeading(fn (Author $record) => 'Reset password untuk '.$record->name)
                    ->modalDescription('Password lama langsung tidak berlaku. Sampaikan password baru ini ke peserta melalui kanal resmi.')
                    ->schema([
                        TextInput::make('password')
                            ->label('Password baru')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->helperText('Minimal 8 karakter.'),
                    ])
                    ->action(function (array $data, Author $record): void {
                        $record->forceFill(['password' => Hash::make($data['password'])])->save();

                        Notification::make()
                            ->title('Password '.$record->name.' berhasil direset.')
                            ->body('Sampaikan password baru ke peserta; mereka dapat langsung login.')
                            ->success()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->emptyStateIcon('heroicon-o-users')
            ->emptyStateHeading('Belum ada akun author')
            ->emptyStateDescription('Akun akan muncul di sini setelah peserta mendaftar melalui portal.');
    }
}
