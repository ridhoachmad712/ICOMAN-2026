<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_id',
        'method',
        'gateway_name',
        'gateway_reference',
        'gateway_payment_id',
        'amount',
        'status',
        'raw_response',
        'checkout_url',
        'notification_history',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'raw_response' => 'array',
            'notification_history' => 'array',
        ];
    }

    /**
     * Order yang masa berlakunya di gateway sudah habis.
     *
     * Kasera mencantumkan expires_at pada permintaan pembayaran dan menolak
     * pembayaran sesudahnya, jadi order seperti ini tidak lagi menahan apa pun
     * — walau statusnya di sini masih "initiated" sampai ada yang menyelaraskan.
     */
    public function hasExpired(): bool
    {
        $expiresAt = $this->raw_response['expires_at'] ?? null;

        if (! is_string($expiresAt)) {
            return false;
        }

        return rescue(
            fn (): bool => now()->greaterThan(Carbon::parse($expiresAt)),
            false,
            false,
        );
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
