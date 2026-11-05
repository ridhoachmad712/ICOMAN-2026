<?php

namespace App\Filament\Resources\PresentationRooms;

use App\Models\RoomPresentation;
use App\Services\RoomManager;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tombol absensi dan penilaian. Dipakai di halaman Room Saya (moderator) dan
 * di daftar presenter tiap room (admin_room, bila perlu menggantikan
 * moderator). Aturannya sendiri ada di RoomManager.
 */
class ModerationActions
{
    /**
     * Satu alur bertahap untuk tiap presenter: kehadiran, penilaian, lalu tinjau
     * dan simpan. Absen sering dilakukan di awal sesi dan nilai setelah presenter
     * tampil, jadi moderator boleh menyimpan absen dulu dan menilai nanti.
     */
    public static function moderate(): Action
    {
        return Action::make('moderate')
            ->label(fn (RoomPresentation $record): string => match (true) {
                ! $record->attendance => 'Absen',
                $record->attended() && ! $record->isScored() => 'Lanjut menilai',
                default => 'Ubah',
            })
            ->icon(fn (RoomPresentation $record): string => $record->attended() && ! $record->isScored()
                ? 'heroicon-o-star'
                : 'heroicon-o-clipboard-document-check')
            ->button()
            ->size('sm')
            ->outlined(fn (RoomPresentation $record): bool => ! self::hasPendingStep($record))
            ->color(fn (RoomPresentation $record): string => self::hasPendingStep($record) ? 'primary' : 'gray')
            ->modalHeading(fn (RoomPresentation $record): string => 'Absensi dan penilaian '.$record->submission?->submission_number)
            ->modalDescription(fn (RoomPresentation $record): ?string => $record->submission?->title)
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Simpan')
            ->fillForm(fn (RoomPresentation $record): array => [
                'attendance' => $record->attendance ?? 'present',
                'presenter_name' => $record->presenter_name ?? self::writers($record)[0] ?? null,
                'scoring' => $record->attended() ? 'now' : 'later',
                'scores' => $record->presenter_scores ?? [],
                'notes' => $record->moderator_notes,
            ])
            ->startOnStep(fn (RoomPresentation $record): int => $record->attended() && ! $record->isScored() ? 2 : 1)
            ->modifyWizardUsing(fn (Wizard $wizard): Wizard => $wizard
                ->nextAction(fn (Action $action): Action => $action->label('Lanjut'))
                ->previousAction(fn (Action $action): Action => $action->label('Kembali')))
            ->steps([
                self::attendanceStep(),
                self::scoringStep(),
                self::reviewStep(),
            ])
            ->visible(fn (RoomPresentation $record): bool => self::mayModerate($record))
            ->action(function (array $data, RoomPresentation $record, Action $action): void {
                $scoresNow = in_array($data['attendance'], RoomPresentation::ATTENDED, true) && ($data['scoring'] ?? null) === 'now';

                self::attempt($action, fn () => DB::transaction(function () use ($data, $record, $scoresNow): void {
                    $manager = app(RoomManager::class);
                    $manager->markAttendance($record, $data['attendance'], $data['presenter_name'] ?? null, auth()->user());

                    if ($scoresNow) {
                        $manager->score($record->refresh(), $data['scores'] ?? [], $data['notes'] ?? null, auth()->user());
                    }
                }));

                Notification::make()
                    ->title($scoresNow ? 'Absensi dan nilai tersimpan' : 'Absensi tersimpan')
                    ->success()
                    ->send();
            });
    }

    private static function attendanceStep(): Step
    {
        return Step::make('Kehadiran')
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
                    ->visible(fn (Get $get): bool => self::isAttending($get))
                    ->required(fn (Get $get): bool => self::isAttending($get)),
                ToggleButtons::make('scoring')
                    ->label('Penilaian')
                    ->helperText('Pilih Nilai nanti bila presenter belum tampil. Penilaian bisa dilanjutkan dari tombol Lanjut menilai.')
                    ->options(['now' => 'Nilai sekarang', 'later' => 'Nilai nanti'])
                    ->inline()
                    ->required()
                    ->live()
                    ->visible(fn (Get $get): bool => self::isAttending($get)),
                TextEntry::make('score_warning')
                    ->hiddenLabel()
                    ->state('Presenter ini sudah dinilai. Bila disimpan sebagai Tidak hadir, nilainya akan terhapus.')
                    ->color('danger')
                    ->visible(fn (Get $get, RoomPresentation $record): bool => $get('attendance') === 'absent' && $record->isScored()),
            ]);
    }

    private static function scoringStep(): Step
    {
        $levels = collect(range(RoomPresentation::SCORE_MIN, RoomPresentation::SCORE_MAX))
            ->mapWithKeys(fn (int $score) => [$score => (string) $score])
            ->all();

        $inputs = collect(RoomPresentation::PRESENTER_CRITERIA)
            ->map(fn (array $criterion, string $key) => ToggleButtons::make('scores.'.$key)
                ->label($criterion['label'].' (bobot '.$criterion['weight'].'%)')
                ->options($levels)
                ->inline()
                ->required()
                ->live())
            ->values()
            ->all();

        return Step::make('Penilaian')
            ->visible(fn (Get $get): bool => self::isScoringNow($get))
            ->schema([
                TextEntry::make('score_guide')
                    ->hiddenLabel()
                    ->state('Beri skor 1–5 untuk setiap kriteria (1 = sangat kurang, 5 = sangat baik).')
                    ->color('gray'),
                ...$inputs,
                TextEntry::make('total')
                    ->label('Total nilai presenter')
                    ->state(fn (Get $get): string => self::totalLabel($get))
                    ->weight('bold'),
                Textarea::make('notes')
                    ->label('Catatan moderator')
                    ->helperText('Hanya terlihat oleh panitia.')
                    ->rows(3),
            ]);
    }

    private static function reviewStep(): Step
    {
        return Step::make('Tinjau & simpan')
            ->schema([
                TextEntry::make('review_attendance')
                    ->label('Kehadiran')
                    ->state(fn (Get $get): string => RoomPresentation::ATTENDANCE[$get('attendance')] ?? '—'),
                TextEntry::make('review_presenter')
                    ->label('Yang mempresentasikan')
                    ->state(fn (Get $get): string => $get('presenter_name') ?: '—')
                    ->visible(fn (Get $get): bool => self::isAttending($get)),
                TextEntry::make('review_score')
                    ->label('Nilai presenter')
                    ->state(function (Get $get, RoomPresentation $record): string {
                        if (! self::isAttending($get)) {
                            return 'Tidak dinilai karena tidak hadir';
                        }

                        if (self::isScoringNow($get)) {
                            return self::totalLabel($get);
                        }

                        return $record->isScored()
                            ? number_format((float) $record->presenter_score, 2, ',', '.').' / 100 (tidak berubah)'
                            : 'Dinilai nanti';
                    })
                    ->weight('bold'),
            ]);
    }

    private static function isAttending(Get $get): bool
    {
        return in_array($get('attendance'), RoomPresentation::ATTENDED, true);
    }

    private static function isScoringNow(Get $get): bool
    {
        return self::isAttending($get) && $get('scoring') === 'now';
    }

    private static function totalLabel(Get $get): string
    {
        return number_format(
            RoomPresentation::presenterTotal(array_map('intval', array_filter((array) $get('scores')))), 2, ',', '.',
        ).' / 100';
    }

    private static function hasPendingStep(RoomPresentation $record): bool
    {
        return ! $record->attendance || ($record->attended() && ! $record->isScored());
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
