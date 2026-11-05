<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Room presentasi paralel, dipandu satu moderator. */
class PresentationRoom extends Model
{
    protected $fillable = [
        'edition_id',
        'name',
        'topic_id',
        'starts_at',
        'ends_at',
        'meeting_url',
        'meeting_note',
        'moderator_id',
        'is_published',
        'scores_submitted_at',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_published' => 'boolean',
            'scores_submitted_at' => 'datetime',
            'order' => 'integer',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function presentations(): HasMany
    {
        return $this->hasMany(RoomPresentation::class);
    }

    /** Nilai sudah dikirim moderator; hanya admin_room yang bisa membukanya. */
    public function isLocked(): bool
    {
        return $this->scores_submitted_at !== null;
    }

    /** Room edisi aktif yang dipandu moderator ini. */
    public function scopeModeratedBy(Builder $query, ?User $moderator): Builder
    {
        return $query
            ->where('edition_id', currentEdition()?->id ?? -1)
            ->where('moderator_id', $moderator?->id ?? -1);
    }

    /**
     * Kemajuan penilaian: semua presenter sudah diabsen dan yang hadir sudah
     * dinilai. Yang tidak hadir dianggap selesai karena memang tidak dinilai.
     *
     * @return array{total: int, unmarked: int, unscored: int, absent: int, done: int, percent: int, ready: bool}
     */
    public function scoringProgress(): array
    {
        $total = $this->presentations->count();
        $unmarked = $this->presentations->whereNull('attendance')->count();
        $unscored = $this->presentations
            ->filter(fn (RoomPresentation $item): bool => $item->attended() && ! $item->isScored())
            ->count();
        $done = $total - $unmarked - $unscored;

        return [
            'total' => $total,
            'unmarked' => $unmarked,
            'unscored' => $unscored,
            'absent' => $this->presentations->where('attendance', 'absent')->count(),
            'done' => $done,
            'percent' => $total > 0 ? (int) floor($done / $total * 100) : 0,
            'ready' => $total > 0 && $done === $total,
        ];
    }

    /** Apa yang masih kurang sebelum penilaian bisa dikirim; null bila sudah lengkap. */
    public function scoringGap(): ?string
    {
        $progress = $this->scoringProgress();

        if ($progress['ready']) {
            return null;
        }

        if ($progress['total'] === 0) {
            return 'Room ini belum punya presenter.';
        }

        return collect([
            $progress['unmarked'] ? $progress['unmarked'].' presenter belum diabsen' : null,
            $progress['unscored'] ? $progress['unscored'].' presenter hadir belum dinilai' : null,
        ])->filter()->implode(', ').'.';
    }

    public function scheduleLabel(): ?string
    {
        if (! $this->starts_at) {
            return null;
        }

        return $this->starts_at->translatedFormat('l, d M Y · H:i')
            .($this->ends_at ? '–'.$this->ends_at->format('H:i') : '');
    }
}
