<?php

namespace App\Console\Commands;

use App\Models\Registration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Bukti transfer dulu tersimpan di disk publik sehingga punya URL /storage/...
 * yang bisa ditebak (id media berurutan). Perintah ini memindahkannya ke disk
 * privat `payment_proofs`, memverifikasi isi salinan sebelum menghapus yang
 * lama, dan aman dijalankan ulang bila sempat terputus.
 */
class ProtectPaymentProofs extends Command
{
    protected $signature = 'icoman:protect-payment-proofs {--fix : Pindahkan berkas; tanpa ini hanya melaporkan}';

    protected $description = 'Pindahkan bukti transfer lama ke penyimpanan privat, memverifikasi isi sebelum menghapus salinan publik.';

    private const DISK = 'payment_proofs';

    public function handle(): int
    {
        $destination = Storage::disk(self::DISK);
        $public = Storage::disk('public');
        $moved = $pending = 0;

        $media = Media::where('model_type', (new Registration)->getMorphClass())
            ->where('collection_name', 'payment_proof')
            ->get();

        foreach ($media as $item) {
            $path = $item->getPathRelativeToRoot();
            if (str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, ':')) {
                throw new \RuntimeException('Path media tidak aman: '.$item->id);
            }

            if ($item->disk === self::DISK && ! $public->exists($path)) {
                continue;
            }

            $pending++;
            $this->line("Media {$item->id} ({$item->disk}): {$path}");

            if (! $this->option('fix')) {
                continue;
            }

            if ($item->disk !== self::DISK) {
                $source = Storage::disk($item->disk);
                $bytes = $source->get($path);
                if (! is_string($bytes) || ! $destination->put($path, $bytes)
                    || hash('sha256', $bytes) !== hash('sha256', $destination->get($path))) {
                    throw new \RuntimeException('Salinan privat tidak terverifikasi untuk media '.$item->id);
                }
                $item->update(['disk' => self::DISK, 'conversions_disk' => self::DISK]);
                if (! $source->delete($path)) {
                    throw new \RuntimeException('Gagal menghapus berkas asal media '.$item->id);
                }
            }

            // Bereskan sisa salinan publik dari run yang terputus, hanya bila isinya sama.
            if ($public->exists($path)) {
                if (! $destination->exists($path) || hash('sha256', $public->get($path)) !== hash('sha256', $destination->get($path))) {
                    throw new \RuntimeException('Salinan publik/privat berbeda untuk media '.$item->id);
                }
                if (! $public->delete($path)) {
                    throw new \RuntimeException('Gagal menghapus salinan publik media '.$item->id);
                }
            }

            $directory = dirname($path);
            if ($directory !== '.' && $public->exists($directory) && $public->allFiles($directory) === []) {
                $public->deleteDirectory($directory);
            }

            $moved++;
        }

        if (! $this->option('fix')) {
            $this->info($pending
                ? "{$pending} bukti transfer masih terjangkau publik. Jalankan ulang dengan --fix."
                : 'Semua bukti transfer sudah di penyimpanan privat.');

            return self::SUCCESS;
        }

        $this->info("{$moved} bukti transfer dipindahkan ke penyimpanan privat dan terverifikasi.");

        return self::SUCCESS;
    }
}
