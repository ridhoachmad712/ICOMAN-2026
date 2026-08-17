<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

class Page extends Model
{
    use HasFactory, HasTranslations, HasSlug;

    protected $fillable = [
        'edition_id',
        'slug',
        'title',
        'content',
        'meta_title',
        'meta_description',
        'is_published',
    ];

    public array $translatable = ['title', 'content', 'meta_title', 'meta_description'];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(fn (self $model) => $model->slugSource())
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite();
    }

    /** Ambil teks judul (translatable) untuk sumber slug. */
    protected function slugSource(): string
    {
        $title = $this->getTranslations('title');

        return (string) (collect($title)->filter()->first() ?? 'page');
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }
}
