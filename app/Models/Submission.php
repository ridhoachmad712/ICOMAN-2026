<?php

namespace App\Models;

use App\Notifications\LoaIssued;
use App\Notifications\SubmissionStatusChanged;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Submission extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    /** Batas jumlah kata abstract (wajib bahasa Inggris). */
    public const ABSTRACT_MIN_WORDS = 150;

    public const ABSTRACT_MAX_WORDS = 500;

    /** Target jurnal penerbitan paper. */
    public const JOURNAL_TARGETS = [
        'regular' => 'Jurnal Reguler',
        'sinta3' => 'Jurnal SINTA 3',
    ];

    public const STATUSES = [
        'extended_abstract_draft',
        'extended_abstract_submitted',
        'extended_abstract_under_review',
        'revision_required',
        'accepted',
        'rejected',
    ];

    public const STATUS_LABELS = [
        'extended_abstract_draft' => 'Draft abstract',
        'extended_abstract_submitted' => 'Abstract terkirim',
        'extended_abstract_under_review' => 'Verifikasi reviewer',
        'revision_required' => 'Perlu revisi',
        'accepted' => 'Accepted',
        'rejected' => 'Tidak lolos',
    ];

    /**
     * Status di mana author boleh menyunting & (kembali) mengirim abstract.
     * `revision_required` membuka kembali editor untuk siklus revise & resubmit.
     */
    public const AUTHOR_EDITABLE_STATUSES = [
        'extended_abstract_draft',
        'revision_required',
    ];

    protected $fillable = [
        'edition_id',
        'author_id',
        'topic_id',
        'submission_number',
        'title',
        'abstract',
        'keywords',
        'extended_abstract_draft_saved_at',
        'extended_abstract_submitted_at',
        'loa_issued_at',
        'full_paper_submitted_at',
        'sinta3_offered',
        'journal_target',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'extended_abstract_submitted_at' => 'datetime',
            'extended_abstract_draft_saved_at' => 'datetime',
            'loa_issued_at' => 'datetime',
            'full_paper_submitted_at' => 'datetime',
            'sinta3_offered' => 'boolean',
            'keywords' => 'array',
        ];
    }

    /** Jumlah kata abstract (perkiraan berbasis pemisah spasi). */
    public function abstractWordCount(): int
    {
        $text = trim((string) $this->abstract);

        return $text === '' ? 0 : count(preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY));
    }

    /** Abstract valid bila jumlah kata dalam rentang yang diizinkan. */
    public function hasValidAbstract(): bool
    {
        $count = $this->abstractWordCount();

        return $count >= self::ABSTRACT_MIN_WORDS && $count <= self::ABSTRACT_MAX_WORDS;
    }

    /**
     * Bagian dokumen abstract untuk ditampilkan/di-PDF-kan. Dipertahankan
     * sebagai satu bagian "Abstract" agar komponen dokumen & PDF tetap bekerja.
     *
     * @return array<string, array{label: string, html: string, text: string}>
     */
    public function extendedAbstractSections(): array
    {
        $text = trim((string) $this->abstract);

        return [
            'abstract' => [
                'label' => 'Abstract',
                'html' => $text === '' ? '' : nl2br(e($text)),
                'text' => $text,
            ],
        ];
    }

    public function isLoaIssued(): bool
    {
        return $this->loa_issued_at !== null;
    }

    public function journalTargetLabel(): string
    {
        return self::JOURNAL_TARGETS[$this->journal_target] ?? self::JOURNAL_TARGETS['regular'];
    }

    /** Ada minimal satu reviewer (yang sudah menilai) merekomendasikan jalur SINTA 3. */
    public function reviewsRecommendSinta3(): bool
    {
        return $this->reviewAssignments()
            ->whereHas('review', fn ($query) => $query->where('recommends_sinta3', true))
            ->exists();
    }

    /** Naskah lengkap (full paper) yang diunggah penulis. */
    public function fullPaperMedia(): ?\Spatie\MediaLibrary\MediaCollections\Models\Media
    {
        return $this->getMedia('camera_ready')->sortByDesc('id')->first();
    }

    public function hasFullPaper(): bool
    {
        return $this->fullPaperMedia() !== null;
    }

    /** Presenter boleh mengirim full paper hanya setelah LOA terbit & registrasi lunas. */
    public function canSubmitFullPaper(): bool
    {
        return $this->status === 'accepted'
            && $this->isLoaIssued()
            && $this->registrations()->where('status', 'paid')->exists();
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

        $this->snapshot('status:'.$this->status.'->'.$status);
        $this->status = $status;

        // LOA terbit OTOMATIS saat naskah diterima: stamp waktu terbit + tawaran SINTA 3
        // ditentukan dari rekomendasi reviewer. Cukup satu email (LoaIssued).
        $justIssuedLoa = $status === 'accepted' && $this->loa_issued_at === null;
        if ($justIssuedLoa) {
            $this->loa_issued_at = now();
            $this->sinta3_offered = $this->reviewsRecommendSinta3();
        }

        $this->save();

        // Notifikasi email ke author (corresponding utama) di setiap perubahan status.
        if ($justIssuedLoa) {
            $this->author?->notify(new LoaIssued($this));
        } else {
            $this->author?->notify(new SubmissionStatusChanged($this));
        }

        return $this;
    }

    public function currentReviewPhase(): ?string
    {
        return match ($this->status) {
            'extended_abstract_submitted', 'extended_abstract_under_review' => 'extended_abstract',
            default => null,
        };
    }

    public function snapshot(string $event): void
    {
        \Illuminate\Support\Facades\DB::table('submission_versions')->insert([
            'submission_id' => $this->id, 'event' => $event,
            'snapshot' => json_encode([
                'title' => $this->title, 'abstract' => $this->abstract, 'status' => $this->status,
                'keywords' => $this->keywords, 'authors' => $this->authors()->get()->toArray(),
                'reviews' => $this->reviewAssignments()->with('review')->get()->toArray(),
                'actor_id' => auth('web')->id() ?? auth('author')->id(),
                'actor_guard' => auth('web')->check() ? 'web' : 'author',
            ]), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function registerMediaCollections(): void
    {
        $mimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        $this->addMediaCollection('camera_ready')->useDisk('papers')->acceptsMimeTypes($mimes);
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
