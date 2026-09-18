<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Pengajuan institusi co-host.
 *
 * Satu co-host berarti beberapa paper gratis, jadi tidak bisa langsung jadi
 * seperti pendaftaran peserta: pengajuan masuk sebagai `pending` dan baru
 * memberi apa-apa setelah panitia menyetujuinya.
 */
class CoHost extends Model implements HasMedia
{
    use InteractsWithMedia;

    /** Jumlah paper gratis per co-host. Ditetapkan panitia, bukan diminta pemohon. */
    public const FREE_PAPERS = 4;

    public const TYPES = [
        'university' => 'Perguruan Tinggi',
        'research' => 'Lembaga Riset',
        'association' => 'Asosiasi / Himpunan',
        'industry' => 'Industri / Perusahaan',
        'government' => 'Pemerintah',
        'other' => 'Lainnya',
    ];

    public const STATUSES = [
        'pending' => 'Menunggu tinjauan',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
    ];

    protected $fillable = [
        'author_id',
        'edition_id',
        'institution_name',
        'institution_type',
        'country',
        'website',
        'pic_position',
        'status',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
        'voucher_id',
        'sponsor_id',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->useDisk('public')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->width(400)->format('webp')->nonQueued();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function typeLabel(): ?string
    {
        return self::TYPES[$this->institution_type] ?? $this->institution_type;
    }

    /** Invoice kemitraan co-host ini, bila sudah terbit. */
    public function registration(): ?Registration
    {
        return $this->author?->registrations()
            ->where('edition_id', $this->edition_id)
            ->whereNull('submission_id')
            ->whereHas('registrationFee', fn (Builder $query) => $query
                ->where('audience', 'cohost')
                ->where('edition_id', $this->edition_id))
            ->latest()
            ->first();
    }

    /**
     * Kemitraan berjalan penuh: sudah disetujui DAN biayanya lunas. Voucher baru
     * bisa dipakai penulisnya pada keadaan ini.
     */
    public function isActive(): bool
    {
        return $this->isApproved() && $this->registration()?->status === 'paid';
    }
}
