<?php

namespace App\Services;

use App\Exceptions\SpyReadOnlyViolation;
use App\Models\Author;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * "Lihat sebagai peserta": panitia masuk ke akun peserta untuk mencocokkan apa
 * yang peserta lihat, tanpa password dan tanpa bisa mengubah apa pun.
 *
 * Penguncian ada di lapisan database, bukan pada tombol di halaman: selama
 * permintaan portal berjalan dalam sesi ini, semua perintah tulis ditolak, dari
 * jalur mana pun datangnya. Panel admin di tab lain tidak ikut terkunci.
 */
class SpySession
{
    private const KEY = 'spy_session';

    /** Tabel milik kerangka kerja yang tetap harus bisa ditulis agar aplikasi berjalan. */
    private const WRITABLE_TABLES = ['sessions', 'cache', 'cache_locks'];

    private bool $locked = false;

    private bool $listening = false;

    public static function canStart(?User $user): bool
    {
        return (bool) $user?->managesSubmissions();
    }

    public function start(User $admin, Author $author): void
    {
        Auth::guard('author')->login($author);
        session()->put(self::KEY, ['author_id' => $author->id, 'admin_id' => $admin->id]);

        Log::info('Sesi lihat sebagai peserta dimulai', $this->context($admin->id, $author->id));
    }

    public function stop(): void
    {
        $session = session(self::KEY);

        if (! $session) {
            return;
        }

        // Bukan Auth::logout(): itu menulis remember_token milik peserta.
        $guard = Auth::guard('author');
        session()->forget([self::KEY, $guard->getName(), 'password_hash_author']);
        $guard->forgetUser();

        Log::info('Sesi lihat sebagai peserta selesai', $this->context($session['admin_id'], $session['author_id']));
    }

    public function active(): bool
    {
        return session()->has(self::KEY);
    }

    /** Admin yang memulai masih login dan masih berhak, dan akun yang dilihat masih sama. */
    public function isValid(): bool
    {
        $session = session(self::KEY);
        $admin = Auth::guard('web')->user();

        return $session
            && $admin?->getKey() === $session['admin_id']
            && self::canStart($admin)
            && Auth::guard('author')->id() === $session['author_id'];
    }

    public function adminName(): ?string
    {
        return Auth::guard('web')->user()?->name;
    }

    public function lock(): void
    {
        $this->listen();
        $this->locked = true;
    }

    public function unlock(): void
    {
        $this->locked = false;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    private function listen(): void
    {
        if ($this->listening) {
            return;
        }

        $this->listening = true;

        DB::connection()->beforeExecuting(function (string $query): void {
            if ($this->locked && $this->isWrite($query)) {
                throw new SpyReadOnlyViolation;
            }
        });
    }

    private function isWrite(string $query): bool
    {
        if (preg_match('/^\s*(select|show|describe|explain|pragma|with)\b/i', $query)) {
            return false;
        }

        if (preg_match('/^\s*(?:insert\s+(?:or\s+\w+\s+)?into|replace\s+into|update|delete\s+from)\s+[`"\[]?(\w+)/i', $query, $table)) {
            return ! in_array($table[1], self::WRITABLE_TABLES, true);
        }

        return true;
    }

    /** @return array<string, int|string|null> */
    private function context(int $adminId, int $authorId): array
    {
        return ['admin_id' => $adminId, 'author_id' => $authorId, 'ip' => request()->ip()];
    }
}
