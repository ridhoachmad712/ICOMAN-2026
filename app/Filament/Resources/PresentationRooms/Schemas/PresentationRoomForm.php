<?php

namespace App\Filament\Resources\PresentationRooms\Schemas;

use App\Models\Topic;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PresentationRoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Room')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama room')
                            ->placeholder('Room 1')
                            ->required()
                            ->maxLength(255),
                        Select::make('topic_id')
                            ->label('Sub-tema')
                            ->relationship('topic', 'id', fn (Builder $query) => $query
                                ->where('edition_id', currentEdition()?->id ?? -1)
                                ->orderBy('order'))
                            ->getOptionLabelFromRecordUsing(fn (Topic $record): string => (string) $record->title)
                            ->helperText('Dipakai tombol "Bagi otomatis" untuk mengelompokkan paper. Kosongkan untuk room campuran.'),
                        Select::make('moderator_id')
                            ->label('Moderator')
                            ->relationship('moderator', 'name', fn (Builder $query) => $query->role('moderator_room'))
                            ->searchable()
                            ->preload()
                            ->helperText('Akun dengan peran moderator_room. Satu room satu moderator.'),
                        TextInput::make('order')
                            ->label('Urutan')
                            ->numeric()
                            ->default(0),
                    ]),

                Section::make('Jadwal dan tautan')
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('starts_at')->label('Mulai')->seconds(false),
                        DateTimePicker::make('ends_at')->label('Selesai')->seconds(false)->after('starts_at'),
                        TextInput::make('meeting_url')
                            ->label('Tautan room')
                            ->url()
                            ->maxLength(255)
                            ->helperText('Tautan breakout room. Hanya tampil bagi presenter yang berhak mengikuti Zoom.'),
                        TextInput::make('meeting_note')
                            ->label('Keterangan room')
                            ->placeholder('Breakout Room 3')
                            ->maxLength(255),
                        Toggle::make('is_published')
                            ->label('Tampilkan di dashboard presenter')
                            ->helperText('Selama mati, presenter belum melihat room, jadwal, dan urutan tampilnya.'),
                    ]),
            ]);
    }
}
