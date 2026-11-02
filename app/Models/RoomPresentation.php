<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu paper yang tampil di sebuah room, beserta absensi, nilai moderator,
 * dan peringkat pemenang yang ditetapkan admin_room.
 */
class RoomPresentation extends Model
{
    public const ATTENDANCE = [
        'present' => 'Hadir',
        'represented' => 'Diwakili co-author',
        'absent' => 'Tidak hadir',
    ];

    /** Kehadiran yang dihitung "tampil": syarat dinilai dan syarat sertifikat. */
    public const ATTENDED = ['present', 'represented'];

    /**
     * Rubrik penilaian presenter oleh moderator: skor 1–5 per kriteria dengan
     * bobot (persen). Nilai tertimbang = skor x bobot / 5, sehingga totalnya
     * 0–100. Paper tidak dinilai moderator.
     */
    public const PRESENTER_CRITERIA = [
        'mastery' => ['label' => 'Penguasaan materi', 'weight' => 25],
        'structure' => ['label' => 'Sistematika dan kejelasan presentasi', 'weight' => 20],
        'communication' => ['label' => 'Kemampuan komunikasi dan penyampaian', 'weight' => 20],
        'slides' => ['label' => 'Kualitas materi dan slide presentasi', 'weight' => 15],
        'qa' => ['label' => 'Kemampuan menjawab pertanyaan (Q&A)', 'weight' => 20],
    ];

    public const SCORE_MIN = 1;

    public const SCORE_MAX = 5;

    public const AWARD_CATEGORIES = [
        'best_paper' => 'Best Paper',
        'best_presenter' => 'Best Presenter',
    ];

    /** Jumlah pemenang per kategori untuk seluruh konferensi. */
    public const WINNERS = 3;

    protected $fillable = [
        'presentation_room_id',
        'submission_id',
        'order',
        'attendance',
        'presenter_name',
        'attendance_marked_at',
        'attendance_marked_by',
        'paper_scores',
        'presenter_scores',
        'paper_score',
        'presenter_score',
        'moderator_notes',
        'scored_at',
        'scored_by',
        'best_paper_rank',
        'best_presenter_rank',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'attendance_marked_at' => 'datetime',
            'paper_scores' => 'array',
            'presenter_scores' => 'array',
            'paper_score' => 'decimal:2',
            'presenter_score' => 'decimal:2',
            'scored_at' => 'datetime',
            'best_paper_rank' => 'integer',
            'best_presenter_rank' => 'integer',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(PresentationRoom::class, 'presentation_room_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function attended(): bool
    {
        return in_array($this->attendance, self::ATTENDED, true);
    }

    public function isScored(): bool
    {
        return $this->scored_at !== null;
    }

    /** Nilai tertimbang satu kriteria: skor x bobot / 5. */
    public static function weighted(string $criterion, int $score): float
    {
        return round($score * self::PRESENTER_CRITERIA[$criterion]['weight'] / self::SCORE_MAX, 2);
    }

    /**
     * Total nilai presenter 0–100 dari skor 1–5 per kriteria.
     *
     * @param  array<string, int>  $scores
     */
    public static function presenterTotal(array $scores): float
    {
        $total = 0.0;
        foreach (self::PRESENTER_CRITERIA as $key => $criterion) {
            $total += self::weighted($key, (int) ($scores[$key] ?? 0));
        }

        return round($total, 2);
    }
}
