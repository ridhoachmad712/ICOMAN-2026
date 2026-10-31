<?php

namespace App\Models;

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

    public function scheduleLabel(): ?string
    {
        if (! $this->starts_at) {
            return null;
        }

        return $this->starts_at->translatedFormat('l, d M Y · H:i')
            .($this->ends_at ? '–'.$this->ends_at->format('H:i') : '');
    }
}
