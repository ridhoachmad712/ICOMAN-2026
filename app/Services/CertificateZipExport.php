<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Edition;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Unduhan ZIP sertifikat yang disusun sedikit demi sedikit. Ratusan PDF butuh
 * beberapa menit, lebih lama dari batas waktu nginx (504), jadi pesanan hanya
 * dicatat; panel di halaman Sertifikat lalu memanggil work() berulang, tiap
 * kali bekerja beberapa detik dan menambahkan ke ZIP yang sama. Tidak
 * bergantung pada queue worker/cron. Satu admin memegang satu unduhan (yang
 * baru menggantikan yang lama); statusnya di cache, jadi tak butuh tabel baru.
 */
class CertificateZipExport
{
    public const DIRECTORY = 'certificate-zips';

    private const TTL_HOURS = 24;

    /** Lama kerja satu langkah (detik), jauh di bawah batas waktu nginx. */
    public static float $budgetSeconds = 20;

    public function __construct(private CertificatePdf $pdf) {}

    /**
     * Mencatat pesanan ZIP. $ids kosong berarti semua sertifikat manual yang berlaku.
     *
     * @param  list<int>|null  $ids
     * @return array<string, mixed>
     */
    public function start(User $user, Edition $edition, ?array $ids = null): array
    {
        $this->discard($user);

        $status = [
            'token' => (string) Str::uuid(),
            'state' => 'running',
            'label' => $ids === null ? 'Semua sertifikat manual' : count($ids).' sertifikat terpilih',
            'total' => $this->query($edition->id, $ids)->count(),
            'done' => 0,
            'edition_id' => $edition->id,
            'ids' => $ids,
            'started_at' => now()->toIso8601String(),
        ];
        $this->put($user, $status);

        return $status;
    }

    /**
     * Satu langkah penyusunan: menambahkan sertifikat berikutnya ke ZIP sampai
     * jatah waktunya habis. Aman dipanggil bersamaan (kunci per admin).
     *
     * @return array<string, mixed>|null status terkini
     */
    public function work(User $user): ?array
    {
        $status = $this->status($user);
        if (($status['state'] ?? null) !== 'running') {
            return $status;
        }

        $lock = Cache::lock('certificate-zip-work:'.$user->id, 120);
        if (! $lock->get()) {
            return $status; // langkah lain masih berjalan
        }

        try {
            $token = $status['token'];
            $disk = Storage::disk('local');
            $disk->makeDirectory(self::DIRECTORY);

            $done = (int) $status['done'];
            $started = microtime(true);
            $ids = $this->query($status['edition_id'], $status['ids'])->pluck('id')->slice($done)->values();
            $next = (function () use ($ids, $status) {
                foreach ($ids->chunk(20) as $chunk) {
                    yield from $this->query($status['edition_id'], $chunk->all())->get();
                }
            })();

            $this->pdf->zip($next, $disk->path($this->path($token)), function () use (&$done, $started): bool {
                $done++;

                return microtime(true) - $started < self::$budgetSeconds;
            });

            // Dibatalkan di tengah jalan: berkas yang baru ditulis ikut dibuang.
            if (($this->status($user)['token'] ?? null) !== $token) {
                $disk->delete($this->path($token));

                return $this->status($user);
            }

            $finished = $done >= (int) $status['done'] + $ids->count();
            $this->update($user->id, $token, $finished
                ? ['state' => 'ready', 'done' => $done, 'total' => $done, 'size' => $disk->size($this->path($token)), 'finished_at' => now()->toIso8601String()]
                : ['done' => $done]);
        } catch (\Throwable $exception) {
            report($exception);
            $this->update($user->id, $status['token'], ['state' => 'failed', 'error' => $exception->getMessage()]);
        } finally {
            $lock->release();
        }

        return $this->status($user);
    }

    /** @return array<string, mixed>|null */
    public function status(User|int $user): ?array
    {
        return Cache::get($this->key($user));
    }

    /** @param  array<string, mixed>  $status */
    public function put(User|int $user, array $status): void
    {
        Cache::put($this->key($user), $status, now()->addHours(self::TTL_HOURS));
    }

    /** Memperbarui status hanya bila pesanannya masih sama (belum diganti atau dibatalkan). */
    public function update(int $userId, string $token, array $changes): void
    {
        $status = $this->status($userId);
        if (($status['token'] ?? null) === $token) {
            $this->put($userId, array_merge($status, $changes));
        }
    }

    public function path(string $token): string
    {
        return self::DIRECTORY.'/'.$token.'.zip';
    }

    /** @param  list<int>|null  $ids */
    public function query(int $editionId, ?array $ids)
    {
        return Certificate::query()
            ->where('edition_id', $editionId)
            ->when($ids === null, fn ($query) => $query->manual()->valid(), fn ($query) => $query->whereKey($ids))
            ->orderBy('number')
            ->orderBy('id');
    }

    /** Membuang pesanan sebelumnya beserta berkasnya. */
    public function discard(User $user): void
    {
        if ($token = $this->status($user)['token'] ?? null) {
            Storage::disk('local')->delete($this->path($token));
        }
        Cache::forget($this->key($user));
    }

    private function key(User|int $user): string
    {
        return 'certificate-zip:'.($user instanceof User ? $user->id : $user);
    }
}
