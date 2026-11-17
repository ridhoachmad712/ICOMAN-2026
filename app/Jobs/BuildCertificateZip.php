<?php

namespace App\Jobs;

use App\Services\CertificatePdf;
use App\Services\CertificateZipExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Menyusun ZIP sertifikat yang dipesan lewat CertificateZipExport::start().
 *
 * Satu PDF bisa beberapa detik, jadi ratusan sertifikat butuh menit. Antrean
 * database mengulang job yang berjalan lebih lama dari `retry_after` (90 detik),
 * maka tiap putaran hanya bekerja sekitar 45 detik, menambahkan ke ZIP yang
 * sama, lalu menjadwalkan putaran berikutnya sampai selesai.
 */
class BuildCertificateZip implements ShouldQueue
{
    use Queueable;

    /** Lama kerja satu putaran (detik), di bawah retry_after dan batas waktu worker. */
    public static float $budgetSeconds = 45;

    public int $timeout = 85;

    /** Gagal berarti dilaporkan ke admin, bukan diulang diam-diam. */
    public int $tries = 1;

    public function __construct(public int $userId, public string $token) {}

    public function handle(CertificateZipExport $export, CertificatePdf $pdf): void
    {
        $status = $export->status($this->userId);
        if (($status['token'] ?? null) !== $this->token || ($status['state'] ?? null) === 'ready') {
            return; // dibatalkan, diganti pesanan baru, atau sudah selesai
        }

        $export->update($this->userId, $this->token, ['state' => 'running']);

        $disk = Storage::disk('local');
        $disk->makeDirectory(CertificateZipExport::DIRECTORY);
        $path = $export->path($this->token);

        $done = (int) $status['done'];
        $before = $done;
        $started = microtime(true);
        $cancelled = false;

        // Urutan diambil sekali sebagai daftar id, lalu dimuat per 20.
        $ids = $export->query($status['edition_id'], $status['ids'])->pluck('id')->slice($done)->values();
        $remaining = (function () use ($ids, $export, $status) {
            foreach ($ids->chunk(20) as $chunk) {
                yield from $export->query($status['edition_id'], $chunk->all())->get();
            }
        })();

        $pdf->zip($remaining, $disk->path($path), function () use ($export, &$done, &$cancelled, $started): bool {
            $done++;

            if (($export->status($this->userId)['token'] ?? null) !== $this->token) {
                $cancelled = true;

                return false;
            }
            $export->update($this->userId, $this->token, ['done' => $done]);

            return microtime(true) - $started < self::$budgetSeconds;
        });

        if ($cancelled) {
            $disk->delete($path);

            return;
        }

        // Masih ada sisa (dan putaran ini maju): lanjutkan di putaran berikutnya.
        if ($done < $before + $ids->count() && $done > $before) {
            static::dispatch($this->userId, $this->token);

            return;
        }

        $export->update($this->userId, $this->token, [
            'state' => 'ready',
            'done' => $done,
            'total' => $done,
            'size' => $disk->size($path),
            'finished_at' => now()->toIso8601String(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        app(CertificateZipExport::class)->update($this->userId, $this->token, [
            'state' => 'failed',
            'error' => $exception?->getMessage(),
        ]);
    }
}
