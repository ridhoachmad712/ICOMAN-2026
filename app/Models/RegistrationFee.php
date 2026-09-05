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
        'registrant_category',
        'price_regular',
        'currency',
        'idr_exchange_rate',
        'notes',
        'order',
    ];

    public array $translatable = ['category', 'notes'];

    protected function casts(): array
    {
        return [
            'price_regular' => 'decimal:2',
            'idr_exchange_rate' => 'decimal:4',
            'order' => 'integer',
        ];
    }

    public function currentPrice(): string
    {
        return $this->price_regular;
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function quote(): array
    {
        $rate = $this->currency === 'IDR' ? 1 : (float) $this->idr_exchange_rate;
        if (! in_array($this->currency, ['IDR', 'USD'], true) || $rate <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['currency' => app()->getLocale() === 'id'
                ? 'Kurs penagihan belum ditetapkan panitia. Hubungi panitia untuk melanjutkan pembayaran.'
                : 'The committee has not set the billing exchange rate yet. Contact the committee to continue payment.']);
        }

        return [
            'base_amount' => (int) round((float) $this->price_regular * $rate),
            'addon_amount' => 0, 'quoted_addon_amount' => (int) rescue(fn () => siteSettings()->sinta3_fee, 0, false),
            'currency' => 'IDR', 'category' => $this->getTranslations('category'),
            'source_amount' => $this->price_regular, 'source_currency' => $this->currency,
            'exchange_rate' => $rate, 'journal_target' => 'regular', 'legacy' => false,
        ];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }
}
