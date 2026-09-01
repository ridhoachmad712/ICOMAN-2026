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
        'audience',
        'price_early_bird',
        'price_regular',
        'early_bird_deadline',
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
            'early_bird_deadline' => 'date',
            'order' => 'integer',
        ];
    }

    public function currentPrice(): string
    {
        if ($this->price_early_bird !== null
            && $this->early_bird_deadline !== null
            && today()->lte($this->early_bird_deadline)) {
            return $this->price_early_bird;
        }

        return $this->price_regular;
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
