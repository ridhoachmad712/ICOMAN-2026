<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    /** Kontrol akses ke panel admin Filament (guard web). */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['superadmin', 'admin_registrasi', 'reviewer', 'content_admin']);
    }

    /**
     * Label & warna badge peran untuk topbar admin. Urutan array = prioritas:
     * pengguna dengan beberapa peran ditampilkan sebagai peran tertingginya.
     */
    public const ROLE_BADGES = [
        'superadmin' => ['label' => 'Super Admin', 'color' => 'danger'],
        'admin_registrasi' => ['label' => 'Admin Registrasi', 'color' => 'info'],
        'content_admin' => ['label' => 'Admin Konten', 'color' => 'warning'],
        'reviewer' => ['label' => 'Reviewer', 'color' => 'success'],
    ];

    /** @return array{label: string, color: string} */
    public function roleBadge(): array
    {
        foreach (self::ROLE_BADGES as $role => $badge) {
            if ($this->hasRole($role)) {
                return $badge;
            }
        }

        return ['label' => 'Tanpa Peran', 'color' => 'gray'];
    }

    public function isSuperadmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    public function isAdminRegistrasi(): bool
    {
        return $this->hasAnyRole(['admin_registrasi', 'superadmin']);
    }

    public function isReviewer(): bool
    {
        return $this->hasRole('reviewer');
    }

    /** Paper yang ditugaskan ke user ini sebagai reviewer. */
    /**
     * Sub-tema yang dikuasai reviewer ini. Kosong berarti "siap untuk semua
     * sub-tema" — lihat scopeExpertIn().
     */
    public function topics(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Topic::class);
    }

    /**
     * Reviewer yang pantas menilai sebuah sub-tema: yang kepakarannya mencakup
     * topik itu, ditambah yang kepakarannya belum diisi sama sekali.
     */
    public function scopeExpertIn(\Illuminate\Database\Eloquent\Builder $query, ?int $topicId): \Illuminate\Database\Eloquent\Builder
    {
        if (! $topicId) {
            return $query;
        }

        return $query->where(fn (\Illuminate\Database\Eloquent\Builder $inner) => $inner
            ->whereHas('topics', fn (\Illuminate\Database\Eloquent\Builder $topics) => $topics->whereKey($topicId))
            ->orWhereDoesntHave('topics'));
    }

    public function reviewAssignments(): HasMany
    {
        return $this->hasMany(ReviewAssignment::class, 'reviewer_id');
    }

    // ── Multi-factor authentication (opsional, opt-in via halaman Profile) ──

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }
}
