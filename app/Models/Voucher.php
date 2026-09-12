<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kode voucher co-host: satu kode per institusi mitra, dipakai beberapa kali
 * sampai kuotanya habis. Membebaskan biaya registrasi dasar presenter; add-on
 * Jurnal SINTA 3 tetap ditagih seperti biasa.
 */
class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'edition_id',
        'code',
        'host_name',
        'quota',
        'is_active',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'quota' => 'integer',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Kode selalu disimpan huruf besar tanpa spasi supaya pencocokan saat
        // ditukar tidak bergantung cara author mengetiknya.
        static::saving(fn (Voucher $voucher) => $voucher->code = static::normalizeCode($voucher->code));
    }

    public static function normalizeCode(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(VoucherRedemption::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function scopeCode(Builder $query, ?string $code): Builder
    {
        return $query->where('code', static::normalizeCode($code));
    }

    public function usedSlots(): int
    {
        return $this->redemptions()->count();
    }

    public function remainingSlots(): int
    {
        return max(0, $this->quota - $this->usedSlots());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Siap dipakai: aktif, belum kedaluwarsa, dan kuotanya masih ada. */
    public function isRedeemable(): bool
    {
        return $this->is_active && ! $this->isExpired() && $this->remainingSlots() > 0;
    }
}
