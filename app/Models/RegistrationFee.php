<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class RegistrationFee extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'edition_id',
        'category',
        'price_early_bird',
        'price_regular',
        'currency',
        'notes',
        'order',
    ];

    public array $translatable = ['category', 'notes'];

    protected function casts(): array
    {
        return [
            'price_early_bird' => 'decimal:2',
            'price_regular' => 'decimal:2',
            'order' => 'integer',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }
}
