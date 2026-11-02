<?php

namespace App\Services;

use App\Models\Edition;
use App\Models\PresentationRoom;
use App\Models\RoomPresentation;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Semua perubahan pada room presentasi lewat sini, supaya aturannya sama di
 * setiap halaman:
 *
 * - presenter yang dibagi hanya paper yang berhak tampil (readyToPresent);
 * - moderator hanya menyentuh room yang ditugaskan kepadanya, dan hanya
 *   sebelum penilaian room itu dikirim (dikunci);
 * - moderator hanya menilai presenter yang hadir (rubrik 1–5 berbobot);
 * - nilai paper diisi terpisah oleh admin room, bukan oleh moderator;
 * - pemenang ditetapkan admin_room: juara 1–3 per kategori untuk seluruh
 *   konferensi. Nilai moderator hanya acuan.
 */
class RoomManager
{
    /** Paper yang berhak tampil tapi belum punya room. */
    public function unassigned(Edition $edition): Collection
    {
        return Submission::query()
            ->readyToPresent($edition)
            ->whereDoesntHave('roomPresentation')
            ->with('topic')
            ->orderBy('submission_number')
            ->get();
    }

    /**
     * Membagi paper yang belum punya room: ke room dengan sub-tema yang sama
     * bila ada, kalau tidak ke room tanpa sub-tema, dan bila tetap tidak ada,
     * ke room mana saja. Dalam pilihan itu dipilih yang presenternya paling
     * sedikit. Paper yang sudah punya room tidak dipindahkan.
     *
     * @return array{assigned: int, left: int}
     */
    public function distribute(Edition $edition): array
    {
        return DB::transaction(function () use ($edition): array {
            $rooms = PresentationRoom::query()
                ->where('edition_id', $edition->id)
                ->whereNull('scores_submitted_at')
                ->withCount('presentations')
                ->orderBy('order')
                ->lockForUpdate()
                ->get();

            $papers = $this->unassigned($edition);

            if ($rooms->isEmpty()) {
                return ['assigned' => 0, 'left' => $papers->count()];
            }

            $load = $rooms->mapWithKeys(fn (PresentationRoom $room) => [$room->id => $room->presentations_count])->all();
            $assigned = 0;

            foreach ($papers as $paper) {
                $candidates = $rooms->where('topic_id', $paper->topic_id);
                if ($candidates->isEmpty()) {
                    $candidates = $rooms->whereNull('topic_id');
                }
                if ($candidates->isEmpty()) {
                    $candidates = $rooms;
                }

                $room = $candidates->sortBy(fn (PresentationRoom $room) => [$load[$room->id], $room->order, $room->id])->first();
                $this->place($room, $paper);
                $load[$room->id]++;
                $assigned++;
            }

            return ['assigned' => $assigned, 'left' => 0];
        });
    }

    public function assign(PresentationRoom $room, Submission $submission): RoomPresentation
    {
        $edition = $room->edition;

        if (! Submission::query()->readyToPresent($edition)->whereKey($submission->id)->exists()) {
            throw ValidationException::withMessages(['submission' => 'Paper ini belum memenuhi syarat tampil (accepted, lunas, dan full paper masuk).']);
        }

        if ($submission->roomPresentation()->exists()) {
            throw ValidationException::withMessages(['submission' => 'Paper ini sudah punya room.']);
        }

        return $this->place($room, $submission);
    }

    /** Pindah room. Nilai ikut pindah; tidak boleh keluar/masuk room yang terkunci. */
    public function move(RoomPresentation $presentation, PresentationRoom $target): void
    {
        if ($presentation->room->isLocked() || $target->isLocked()) {
            throw ValidationException::withMessages(['room' => 'Penilaian room sudah dikirim. Buka kunci room lebih dulu.']);
        }

        $presentation->update([
            'presentation_room_id' => $target->id,
            'order' => (int) $target->presentations()->max('order') + 1,
        ]);
    }

    public function canModerate(User $user, PresentationRoom $room): bool
    {
        return $user->managesRooms() || $room->moderator_id === $user->id;
    }

    public function markAttendance(RoomPresentation $presentation, string $attendance, ?string $presenterName, User $user): void
    {
        $this->guardModeration($presentation, $user);

        if (! array_key_exists($attendance, RoomPresentation::ATTENDANCE)) {
            throw ValidationException::withMessages(['attendance' => 'Status kehadiran tidak dikenal.']);
        }

        $attended = in_array($attendance, RoomPresentation::ATTENDED, true);

        $presentation->update([
            'attendance' => $attendance,
            'presenter_name' => $attended ? $presenterName : null,
            'attendance_marked_at' => now(),
            'attendance_marked_by' => $user->id,
        ] + ($attended ? [] : [
            // Yang tidak hadir tidak dinilai moderator dan tidak bisa menang.
            // Nilai paper dari penilaian terpisah dibiarkan.
            'presenter_scores' => null,
            'presenter_score' => null,
            'scored_at' => null,
            'scored_by' => null,
            'best_paper_rank' => null,
            'best_presenter_rank' => null,
        ]));
    }

    /**
     * Penilaian presenter oleh moderator dengan rubrik RoomPresentation::
     * PRESENTER_CRITERIA (skor 1–5, ditimbang bobot, total 0–100).
     *
     * @param  array<string, int|string|null>  $scores
     */
    public function score(RoomPresentation $presentation, array $scores, ?string $notes, User $user): void
    {
        $this->guardModeration($presentation, $user);

        if (! $presentation->attended()) {
            throw ValidationException::withMessages(['score' => 'Tandai presenter hadir lebih dulu sebelum menilai.']);
        }

        $clean = $this->clean($scores);

        $presentation->update([
            'presenter_scores' => $clean,
            'presenter_score' => RoomPresentation::presenterTotal($clean),
            'moderator_notes' => $notes,
            'scored_at' => now(),
            'scored_by' => $user->id,
        ]);
    }

    /**
     * Nilai paper (0–100) dari penilaian terpisah, diisi admin room. Kosong
     * berarti belum dinilai.
     */
    public function setPaperScore(RoomPresentation $presentation, int|float|string|null $score): void
    {
        if ($score === null || $score === '') {
            $presentation->update(['paper_score' => null]);

            return;
        }

        if (! is_numeric($score) || $score < 0 || $score > 100) {
            throw ValidationException::withMessages(['paper_score' => 'Nilai paper harus angka 0–100.']);
        }

        $presentation->update(['paper_score' => round((float) $score, 2)]);
    }

    /** Moderator selesai: semua presenter harus sudah diabsen, yang hadir sudah dinilai. */
    public function submitScores(PresentationRoom $room, User $user): void
    {
        if (! $this->canModerate($user, $room)) {
            abort(403);
        }

        $presentations = $room->presentations()->get();
        $unmarked = $presentations->whereNull('attendance')->count();
        $unscored = $presentations->filter(fn (RoomPresentation $item) => $item->attended() && ! $item->isScored())->count();

        if ($unmarked || $unscored) {
            throw ValidationException::withMessages(['room' => "Belum lengkap: {$unmarked} presenter belum diabsen, {$unscored} presenter hadir belum dinilai."]);
        }

        $room->update(['scores_submitted_at' => now()]);
    }

    public function reopen(PresentationRoom $room): void
    {
        $room->update(['scores_submitted_at' => null]);
    }

    /**
     * Menetapkan (atau menghapus, bila $rank null) juara sebuah kategori.
     * Peringkat yang sama di seluruh edisi hanya boleh dimiliki satu paper,
     * jadi pemegang lamanya dilepas.
     */
    public function setRank(RoomPresentation $presentation, string $category, ?int $rank): void
    {
        $column = match ($category) {
            'best_paper' => 'best_paper_rank',
            'best_presenter' => 'best_presenter_rank',
        };

        if ($rank !== null) {
            if ($rank < 1 || $rank > RoomPresentation::WINNERS) {
                throw ValidationException::withMessages(['rank' => 'Peringkat harus 1 sampai '.RoomPresentation::WINNERS.'.']);
            }

            if (! $presentation->attended()) {
                throw ValidationException::withMessages(['rank' => 'Presenter yang tidak hadir tidak dapat ditetapkan sebagai pemenang.']);
            }
        }

        DB::transaction(function () use ($presentation, $column, $rank): void {
            if ($rank !== null) {
                RoomPresentation::query()
                    ->whereKeyNot($presentation->id)
                    ->where($column, $rank)
                    ->whereHas('room', fn ($room) => $room->where('edition_id', $presentation->room->edition_id))
                    ->update([$column => null]);
            }

            $presentation->update([$column => $rank]);
        });
    }

    private function place(PresentationRoom $room, Submission $submission): RoomPresentation
    {
        return RoomPresentation::create([
            'presentation_room_id' => $room->id,
            'submission_id' => $submission->id,
            'order' => (int) $room->presentations()->max('order') + 1,
        ]);
    }

    private function guardModeration(RoomPresentation $presentation, User $user): void
    {
        $room = $presentation->room;

        if (! $this->canModerate($user, $room)) {
            abort(403);
        }

        if ($room->isLocked()) {
            throw ValidationException::withMessages(['room' => 'Penilaian room ini sudah dikirim dan terkunci.']);
        }
    }

    /**
     * @param  array<string, mixed>  $scores
     * @return array<string, int>
     */
    private function clean(array $scores): array
    {
        $clean = [];
        foreach (array_keys(RoomPresentation::PRESENTER_CRITERIA) as $key) {
            $value = $scores[$key] ?? null;

            if (! is_numeric($value) || (int) $value != $value
                || $value < RoomPresentation::SCORE_MIN || $value > RoomPresentation::SCORE_MAX) {
                throw ValidationException::withMessages(['score' => 'Semua kriteria wajib diberi skor 1–5.']);
            }

            $clean[$key] = (int) $value;
        }

        return $clean;
    }
}
