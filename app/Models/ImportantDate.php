<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class ImportantDate extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'edition_id',
        'label',
        'date',
        'is_highlighted',
        'order',
        'kind',
        'closes_at',
    ];

    public array $translatable = ['label'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'closes_at' => 'datetime',
            'is_highlighted' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }
}
