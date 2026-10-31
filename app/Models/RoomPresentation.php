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

    /** Kriteria nilai, masing-masing 0–100; nilai akhir = rata-ratanya. */
    public const PAPER_CRITERIA = [
        'novelty' => 'Kebaruan dan orisinalitas',
        'methodology' => 'Ketepatan metodologi',
        'contribution' => 'Kontribusi dan relevansi',
        'findings' => 'Kualitas temuan dan kesimpulan',
    ];

    public const PRESENTER_CRITERIA = [
        'delivery' => 'Penyampaian dan kejelasan',
        'slides' => 'Kualitas materi/slide',
        'time' => 'Ketepatan waktu',
        'qa' => 'Penguasaan tanya jawab',
    ];

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

    /** Rata-rata nilai kriteria yang terisi, dibulatkan dua desimal. */
    public static function average(array $scores): ?float
    {
        $values = array_filter($scores, fn ($value) => $value !== null && $value !== '');

        return $values === [] ? null : round(array_sum(array_map('floatval', $values)) / count($values), 2);
    }
}
