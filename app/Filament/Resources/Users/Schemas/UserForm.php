<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Topic;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserForm
{
    /**
     * Isian kepakaran hanya masuk akal untuk reviewer. Nilai dari Select
     * relationship berupa id peran, jadi dicocokkan ke id peran `reviewer`.
     */
    private static function hasReviewerRole(mixed $roles): bool
    {
        $reviewerId = Role::where('name', 'reviewer')->value('id');

        return $reviewerId !== null && in_array((string) $reviewerId, array_map('strval', (array) $roles), true);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Akun')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('email')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->helperText('Kosongkan saat edit bila tidak ingin mengganti password.')
                            ->maxLength(255),
                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->required()
                            ->live()
                            ->helperText('superadmin = akses penuh · admin_registrasi = kelola pendaftaran & pembayaran · reviewer = hanya review paper.'),
                    ]),

                Section::make('Kepakaran Reviewer')
                    ->description('Sub-tema yang dikuasai reviewer ini. Dipakai menyaring pilihan saat paper ditugaskan.')
                    // Hanya relevan untuk reviewer; muncul begitu role itu dipilih.
                    ->visible(fn ($get): bool => static::hasReviewerRole($get('roles')))
                    ->schema([
                        Select::make('topics')
                            ->hiddenLabel()
                            ->relationship(name: 'topics', titleAttribute: 'id')
                            ->getOptionLabelFromRecordUsing(fn (Topic $record): string => $record->title)
                            ->multiple()
                            ->preload()
                            ->helperText('Kosongkan bila reviewer ini siap menilai sub-tema apa pun.'),
                    ]),
            ]);
    }
}
