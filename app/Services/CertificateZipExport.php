<?php

namespace App\Services;

use App\Jobs\BuildCertificateZip;
use App\Models\Certificate;
use App\Models\Edition;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Unduhan ZIP sertifikat yang disiapkan di antrean. Ratusan PDF butuh
 * beberapa menit, lebih lama dari batas waktu nginx, jadi permintaan web
 * hanya memesan; halaman Sertifikat menampilkan kemajuannya dan tombol
 * unduh setelah selesai. Satu admin memegang satu unduhan (yang baru
 * menggantikan yang lama), statusnya di cache sehingga tak butuh tabel baru.
 */
class CertificateZipExport
{
    public const DIRECTORY = 'certificate-zips';

    private const TTL_HOURS = 24;

    /**
     * Memesan ZIP. $ids kosong berarti semua sertifikat manual yang berlaku.
     *
     * @param  list<int>|null  $ids
     */
    public function start(User $user, Edition $edition, ?array $ids = null): array
    {
        $this->discard($user);

        $total = $this->query($edition, $ids)->count();
        $status = [
            'token' => (string) Str::uuid(),
            'state' => 'queued',
            'label' => $ids === null ? 'Semua sertifikat manual' : count($ids).' sertifikat terpilih',
            'total' => $total,
            'done' => 0,
            'edition_id' => $edition->id,
            'ids' => $ids,
            'started_at' => now()->toIso8601String(),
        ];
        $this->put($user, $status);

        BuildCertificateZip::dispatch($user->id, $status['token']);

        return $status;
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

    /** Memperbarui status hanya bila pesanannya masih sama (belum diganti yang baru). */
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
    public function query(Edition|int $edition, ?array $ids)
    {
        return Certificate::query()
            ->where('edition_id', $edition instanceof Edition ? $edition->id : $edition)
            ->when($ids === null, fn ($query) => $query->manual()->valid(), fn ($query) => $query->whereKey($ids))
            ->orderBy('number');
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
