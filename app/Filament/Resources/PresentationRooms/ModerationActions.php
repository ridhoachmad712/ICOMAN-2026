<?php

namespace App\Filament\Resources\PresentationRooms;

use App\Models\RoomPresentation;
use App\Services\RoomManager;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;

/**
 * Tombol absensi dan penilaian. Dipakai di halaman Room Saya (moderator) dan
 * di daftar presenter tiap room (admin_room, bila perlu menggantikan
 * moderator). Aturannya sendiri ada di RoomManager.
 */
class ModerationActions
{
    public static function attendance(): Action
    {
        return Action::make('attendance')
            ->label('Absensi')
            ->icon('heroicon-o-clipboard-document-check')
            ->color(fn (RoomPresentation $record): string => $record->attendance ? 'gray' : 'primary')
            ->modalHeading(fn (RoomPresentation $record): string => 'Absensi '.$record->submission?->submission_number)
            ->modalDescription(fn (RoomPresentation $record): ?string => $record->submission?->title)
            ->modalWidth('lg')
            ->fillForm(fn (RoomPresentation $record): array => [
                'attendance' => $record->attendance ?? 'present',
                'presenter_name' => $record->presenter_name ?? self::writers($record)[0] ?? null,
            ])
            ->schema([
                ToggleButtons::make('attendance')
                    ->label('Kehadiran')
                    ->options(RoomPresentation::ATTENDANCE)
                    ->colors(['present' => 'success', 'represented' => 'info', 'absent' => 'danger'])
                    ->inline()
                    ->required()
                    ->live(),
                Select::make('presenter_name')
                    ->label('Yang mempresentasikan')
                    ->options(fn (RoomPresentation $record): array => array_combine(self::writers($record), self::writers($record)))
                    ->visible(fn (Get $get): bool => in_array($get('attendance'), RoomPresentation::ATTENDED, true))
                    ->required(fn (Get $get): bool => in_array($get('attendance'), RoomPresentation::ATTENDED, true)),
            ])
            ->visible(fn (RoomPresentation $record): bool => self::mayModerate($record))
            ->action(function (array $data, RoomPresentation $record, Action $action): void {
                self::attempt($action, fn () => app(RoomManager::class)
                    ->markAttendance($record, $data['attendance'], $data['presenter_name'] ?? null, auth()->user()));

                Notification::make()->title('Absensi tersimpan')->success()->send();
            });
    }

    public static function score(): Action
    {
        $inputs = fn (array $criteria, string $group): array => collect($criteria)
            ->map(fn (string $label, string $key) => TextInput::make($group.'.'.$key)
                ->label($label)
                ->numeric()
                ->integer()
                ->minValue(0)
                ->maxValue(100)
                ->suffix('/ 100')
                ->required())
            ->values()
            ->all();

        return Action::make('score')
            ->label('Nilai')
            ->icon('heroicon-o-star')
            ->color(fn (RoomPresentation $record): string => $record->isScored() ? 'gray' : 'warning')
            ->modalHeading(fn (RoomPresentation $record): string => 'Penilaian '.$record->submission?->submission_number)
            ->modalDescription(fn (RoomPresentation $record): string => $record->submission?->title.' · Presenter: '.($record->presenter_name ?: '—'))
            ->modalWidth('3xl')
            ->fillForm(fn (RoomPresentation $record): array => [
                'paper' => $record->paper_scores ?? [],
                'presenter' => $record->presenter_scores ?? [],
                'notes' => $record->moderator_notes,
            ])
            ->schema([
                Section::make('Paper')
                    ->description('Acuan Best Paper. Skala 0–100 per kriteria; nilai paper = rata-ratanya.')
                    ->columns(2)
                    ->schema($inputs(RoomPresentation::PAPER_CRITERIA, 'paper')),
                Section::make('Presenter')
                    ->description('Acuan Best Presenter. Skala 0–100 per kriteria; nilai presenter = rata-ratanya.')
                    ->columns(2)
                    ->schema($inputs(RoomPresentation::PRESENTER_CRITERIA, 'presenter')),
                Textarea::make('notes')
                    ->label('Catatan moderator')
                    ->helperText('Hanya terlihat oleh panitia.')
                    ->rows(3),
            ])
            ->visible(fn (RoomPresentation $record): bool => $record->attended() && self::mayModerate($record))
            ->action(function (array $data, RoomPresentation $record, Action $action): void {
                self::attempt($action, fn () => app(RoomManager::class)
                    ->score($record, $data['paper'] ?? [], $data['presenter'] ?? [], $data['notes'] ?? null, auth()->user()));

                Notification::make()->title('Nilai tersimpan')->success()->send();
            });
    }

    /** Nama penulis sesuai urutan; paper lama tanpa daftar penulis memakai pengirimnya. */
    public static function writers(RoomPresentation $record): array
    {
        $names = $record->submission?->authors->sortBy('order')->pluck('name')->filter()->values()->all() ?? [];

        return $names ?: array_filter([$record->submission?->author?->name]);
    }

    public static function attempt(Action $action, callable $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Tidak dapat disimpan')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();

            $action->halt();
        }
    }

    private static function mayModerate(RoomPresentation $record): bool
    {
        $user = auth()->user();

        return $user !== null
            && ! $record->room->isLocked()
            && app(RoomManager::class)->canModerate($user, $record->room);
    }
}
