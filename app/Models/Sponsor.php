<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Sponsor extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    /** Tingkatan dukungan. Tidak lagi memisahkan tampilan, tapi tetap disebut saat logonya disentuh. */
    public const TIERS = [
        'platinum' => 'Platinum Sponsor',
        'gold' => 'Gold Sponsor',
        'silver' => 'Silver Sponsor',
        'partner' => 'Partner',
        'media_partner' => 'Media Partner',
    ];

    public function tierLabel(): string
    {
        return self::TIERS[$this->tier] ?? ucwords(str_replace('_', ' ', (string) $this->tier));
    }

    protected $fillable = [
        'edition_id',
        'name',
        'tier',
        'website_url',
        'order',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile()->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Logo tidak di-crop — jaga rasio, cukup batasi lebar & konversi WebP.
        $this->addMediaConversion('thumb')->width(280)->format('webp')->nonQueued();
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }
}
