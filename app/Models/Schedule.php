<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class Schedule extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'edition_id',
        'day_date',
        'time_start',
        'time_end',
        'title',
        'speaker_name',
        'room',
        'session_type',
        'order',
    ];

    public array $translatable = ['title'];

    protected function casts(): array
    {
        return [
            'day_date' => 'date',
            'order' => 'integer',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }
}
