<?php

namespace App\Models;

use App\Filament\Shared\RichContent\EquationBlock;
use App\Notifications\SubmissionStatusChanged;
use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Submission extends Model implements HasMedia, HasRichContent
{
    use HasFactory, InteractsWithMedia, InteractsWithRichContent;

    public const EXTENDED_ABSTRACT_FIELDS = [
        'extended_abstract_abstract' => 'Abstract',
        'extended_abstract_introduction' => 'Introduction',
        'extended_abstract_method' => 'Method',
        'extended_abstract_results_discussion' => 'Results and Discussion',
        'extended_abstract_conclusion' => 'Conclusion',
    ];

    public const STATUSES = [
        'extended_abstract_draft',
        'abstract_submitted',
        'abstract_under_review',
        'abstract_approved',
        'extended_abstract_submitted',
        'extended_abstract_under_review',
        'accepted',
        'rejected',
    ];

    public const STATUS_LABELS = [
        'extended_abstract_draft' => 'Draft extended abstract',
        'abstract_submitted' => 'Abstrak terkirim',
        'abstract_under_review' => 'Abstrak direview',
        'abstract_approved' => 'Lolos review abstrak',
        'extended_abstract_submitted' => 'Extended abstract terkirim',
        'extended_abstract_under_review' => 'Verifikasi extended abstract',
        'accepted' => 'Accepted',
        'rejected' => 'Tidak lolos',
    ];

    protected $fillable = [
        'edition_id',
        'author_id',
        'topic_id',
        'submission_number',
        'title',
        'abstract',
        'abstract_id',
        'keywords',
        'extended_abstract',
        'extended_abstract_abstract',
        'extended_abstract_introduction',
        'extended_abstract_method',
        'extended_abstract_results_discussion',
        'extended_abstract_conclusion',
        'extended_abstract_draft_saved_at',
        'extended_abstract_submitted_at',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'extended_abstract_submitted_at' => 'datetime',
            'extended_abstract_draft_saved_at' => 'datetime',
            'keywords' => 'array',
            'extended_abstract_abstract' => 'array',
            'extended_abstract_introduction' => 'array',
            'extended_abstract_method' => 'array',
            'extended_abstract_results_discussion' => 'array',
            'extended_abstract_conclusion' => 'array',
        ];
    }

    protected function setUpRichContent(): void
    {
        foreach (array_keys(self::EXTENDED_ABSTRACT_FIELDS) as $field) {
            $this->registerRichContent($field)
                ->json()
                ->customBlocks([EquationBlock::class])
                ->fileAttachmentsDisk('local')
                ->fileAttachmentsVisibility('private');
        }
    }

    /**
     * @return array<string, array{label: string, html: string, text: string}>
     */
    public function extendedAbstractSections(): array
    {
        $sections = [];

        foreach (self::EXTENDED_ABSTRACT_FIELDS as $field => $label) {
            $attribute = $this->getRichContentAttribute($field);
            $sections[$field] = [
                'label' => $label,
                'html' => $attribute?->toHtml() ?? '',
                'text' => trim($attribute?->toText() ?? ''),
            ];
        }

        if (collect($sections)->every(fn (array $section) => blank($section['text'])) && filled($this->extended_abstract)) {
            $sections['extended_abstract_abstract'] = [
                'label' => 'Extended Abstract',
                'html' => nl2br(e($this->extended_abstract)),
                'text' => trim($this->extended_abstract),
            ];
        }

        return $sections;
    }

    public function hasCompleteExtendedAbstract(): bool
    {
        return collect(array_keys(self::EXTENDED_ABSTRACT_FIELDS))
            ->every(fn (string $field) => filled(trim($this->getRichContentAttribute($field)?->toText() ?? '')));
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
            ? Str::of($edition->name)->replaceMatches('/[^A-Za-z0-9]/', '')->upper()
            : 'ICOMAN';

        return $code.'-'.strtoupper((string) Str::ulid());
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
        $this->author?->notify(new SubmissionStatusChanged($this));

        return $this;
    }

    public function currentReviewPhase(): ?string
    {
        return match ($this->status) {
            'extended_abstract_submitted', 'extended_abstract_under_review' => 'extended_abstract',
            // Status lama tetap dapat dibuka selama masa transisi data.
            'abstract_under_review' => 'abstract',
            default => null,
        };
    }

    public function registerMediaCollections(): void
    {
        $mimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        $this->addMediaCollection('paper')->singleFile()->acceptsMimeTypes($mimes);
        $this->addMediaCollection('revisions')->acceptsMimeTypes($mimes);
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
