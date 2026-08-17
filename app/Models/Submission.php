<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Submission extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public const STATUSES = [
        'submitted',
        'under_review',
        'revision_required',
        'accepted',
        'rejected',
    ];

    protected $fillable = [
        'edition_id',
        'author_id',
        'topic_id',
        'submission_number',
        'title',
        'abstract',
        'abstract_id',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Submission $submission): void {
            $submission->submitted_at ??= now();

            if (blank($submission->submission_number)) {
                $submission->submission_number = static::generateNumber($submission->edition_id);
            }
        });
    }

    /** Nomor submission unik per-edition, mis. ICOMAN2026-0001. */
    public static function generateNumber(?int $editionId): string
    {
        $edition = $editionId ? Edition::find($editionId) : currentEdition();
        $code = $edition
            ? \Illuminate\Support\Str::of($edition->name)->replaceMatches('/[^A-Za-z0-9]/', '')->upper()
            : 'ICOMAN';

        $seq = static::where('edition_id', $editionId)->count() + 1;

        do {
            $number = sprintf('%s-%04d', $code, $seq);
            $seq++;
        } while (static::where('submission_number', $number)->exists());

        return $number;
    }

    /**
     * Satu-satunya titik ubah status paper. Notifikasi email dikirim di sini
     * (di-wire pada Fase 4) — JANGAN update kolom `status` langsung dari tempat lain.
     */
    public function changeStatus(string $status): self
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException("Status submission tidak valid: {$status}");
        }

        if ($this->status === $status) {
            return $this;
        }

        $this->status = $status;
        $this->save();

        // Notifikasi email ke author (corresponding utama) di setiap perubahan status.
        $this->author?->notify(new \App\Notifications\SubmissionStatusChanged($this));

        return $this;
    }

    public function registerMediaCollections(): void
    {
        $mimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        $this->addMediaCollection('paper')->singleFile()->acceptsMimeTypes($mimes);
        $this->addMediaCollection('camera_ready')->singleFile()->acceptsMimeTypes($mimes);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function authors(): HasMany
    {
        return $this->hasMany(SubmissionAuthor::class);
    }

    public function reviewAssignments(): HasMany
    {
        return $this->hasMany(ReviewAssignment::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }
}
