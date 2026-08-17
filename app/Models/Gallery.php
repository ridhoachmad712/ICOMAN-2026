<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

class Gallery extends Model implements HasMedia
{
    use HasFactory, HasTranslations, InteractsWithMedia;

    protected $fillable = [
        'edition_id',
        'caption',
        'order',
    ];

    public array $translatable = ['caption'];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->fit(Fit::Crop, 400, 400)->format('webp')->nonQueued();
        $this->addMediaConversion('card')->fit(Fit::Crop, 700, 500)->format('webp')->nonQueued();
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }
}
