<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Akun publik (guard `author`), terpisah dari tabel `users` (admin Filament).
 * Konfigurasi guard `author` di config/auth.php dilakukan pada Fase 4.
 */
class Author extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'affiliation',
        'country',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Kirim link reset ke route portal author (bukan route admin default). */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\AuthorResetPassword($token));
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }
}
