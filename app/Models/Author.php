<?php

namespace App\Models;

use App\Notifications\AuthorResetPassword;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Akun publik (guard `author`), terpisah dari tabel `users` (admin Filament).
 * Konfigurasi guard `author` di config/auth.php dilakukan pada Fase 4.
 */
class Author extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    /** Kategori peserta (menentukan tarif). */
    public const CATEGORIES = [
        'student_s1' => 'Mahasiswa S1',
        'general' => 'Dosen / Umum',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'affiliation',
        'country',
        'phone',
        'participation_type',
        'registrant_category',
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
        $this->notify(new AuthorResetPassword($token));
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function isPresenter(): bool
    {
        return $this->participation_type === 'presenter';
    }

    public function isParticipant(): bool
    {
        return $this->participation_type === 'participant';
    }

    public function isStudentS1(): bool
    {
        return $this->registrant_category === 'student_s1';
    }

    /** Kategori efektif untuk filter tarif (default 'general'). */
    public function feeCategory(): string
    {
        return $this->registrant_category === 'student_s1' ? 'student_s1' : 'general';
    }

    public function registrantCategoryLabel(): string
    {
        return self::CATEGORIES[$this->registrant_category] ?? self::CATEGORIES['general'];
    }

    public function hasSeminarAccess(?Edition $edition = null): bool
    {
        $edition ??= currentEdition();

        return (bool) ($edition && $this->registrations()
            ->where('edition_id', $edition->id)
            ->where('status', 'paid')
            ->exists());
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'author';
    }
}
