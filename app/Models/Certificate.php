<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Certificate extends Model
{
    public const TYPES = [
        'presentation' => 'Certificate of Presentation',
        'participation' => 'Certificate of Participation',
    ];

    protected $fillable = [
        'edition_id',
        'author_id',
        'submission_id',
        'submission_author_id',
        'type',
        'number',
        'recipient_name',
        'recipient_affiliation',
        'paper_title',
        'verification_token',
        'issued_at',
        'issued_by',
        'revoked_at',
        'revoke_reason',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function newToken(): string
    {
        return Str::random(40);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function submissionAuthor(): BelongsTo
    {
        return $this->belongsTo(SubmissionAuthor::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function verificationUrl(): string
    {
        return route('certificates.verify', $this->verification_token);
    }

    /** Nama berkas aman untuk unduhan: nomor dengan garis miring diganti. */
    public function fileName(): string
    {
        return str_replace('/', '-', $this->number).'.pdf';
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }
}
