<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Foto yang "berhasil diunggah tapi tidak tampil" hampir selalu berarti berkasnya
 * ada di storage/app/public tetapi tidak dapat dijangkau lewat URL /storage/...,
 * yakni symlink public/storage hilang. `storage:link` bawaan Laravel bisa gagal
 * di shared hosting yang mematikan exec(), jadi perintah ini memakai symlink()
 * langsung dan sekaligus melaporkan penyebab lain yang mungkin.
 */
class CheckMediaStorage extends Command
{
    protected $signature = 'icoman:check-media {--fix : Buat symlink public/storage bila belum ada}';

    protected $description = 'Periksa (dan perbaiki) akses publik ke berkas media yang diunggah.';

    /**
     * Koleksi yang memang ditampilkan di situs publik. Sengaja TIDAK memuat
     * camera_ready (full paper, disk privat) dan payment_proof (bukti bayar,
     * data sensitif) — keduanya harus tetap tidak punya URL publik.
     */
    private const PUBLIC_COLLECTIONS = ['photo', 'logo', 'image', 'thumbnail', 'file'];

    public function handle(): int
    {
        $target = rtrim(config('filesystems.disks.public.root'), DIRECTORY_SEPARATOR.'/');
        $link = public_path('storage');
        $problems = [];

        $this->line('APP_URL           : '.config('app.url'));
        $this->line('Media disk        : '.config('media-library.disk_name'));
        $this->line('Folder penyimpanan: '.$target);

        if (! is_dir($target)) {
            $problems[] = 'Folder penyimpanan tidak ada: '.$target;
        } elseif (! is_writable($target)) {
            $problems[] = 'Folder penyimpanan tidak dapat ditulis: '.$target;
        }

        // Inti masalahnya: apakah /storage terjangkau dari web?
        if (! file_exists($link)) {
            if ($this->option('fix')) {
                if (@symlink($target, $link)) {
                    $this->info('public/storage dibuat -> '.$target);
                } else {
                    $problems[] = 'Gagal membuat symlink public/storage. Buat manual: ln -s ../storage/app/public public/storage';
                }
            } else {
                $problems[] = 'public/storage TIDAK ADA — semua foto akan 404. Jalankan ulang dengan --fix.';
            }
        } else {
            $actual = is_link($link) ? readlink($link) : null;
            $this->line('public/storage    : '.($actual ?? $link.' (folder biasa)'));

            if ($actual !== null) {
                // Target symlink umumnya RELATIF terhadap folder public/, bukan
                // terhadap direktori kerja artisan, jadi harus di-resolve dari sana.
                $resolved = realpath(
                    str_starts_with($actual, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:/', $actual)
                        ? $actual
                        : dirname($link).DIRECTORY_SEPARATOR.$actual
                );

                $this->line('  -> mengarah ke   : '.($resolved ?: '(tidak dapat di-resolve)'));

                if ($resolved === false || $resolved !== realpath($target)) {
                    $problems[] = 'public/storage tidak mengarah ke folder penyimpanan. Target: '.$actual;
                }
            }
        }

        // Cek berkas yang tercatat memang ada — per disk, karena full paper
        // disimpan di disk privat, bukan di disk publik.
        $rows = DB::table('media')->select('id', 'disk', 'collection_name', 'file_name')->get();
        $missing = [];
        $perDisk = [];
        foreach ($rows as $row) {
            $disk = $row->disk ?: config('media-library.disk_name');
            $perDisk[$disk] = ($perDisk[$disk] ?? 0) + 1;

            if (! Storage::disk($disk)->exists($row->id.'/'.$row->file_name)) {
                $missing[] = $row->collection_name.' #'.$row->id.' ('.$disk.')';
            }
        }

        $perDisk[config('media-library.disk_name')] ??= 0;
        foreach ($perDisk as $disk => $count) {
            $this->line('Berkas di disk '.str_pad($disk, 8).': '.$count);
        }

        // Rincian per koleksi + disk: memperlihatkan apakah foto benar-benar
        // tersimpan, dan di disk mana.
        $byCollection = DB::table('media')
            ->selectRaw('collection_name, disk, COUNT(*) as jumlah')
            ->groupBy('collection_name', 'disk')
            ->orderBy('collection_name')
            ->get();
        if ($byCollection->isNotEmpty()) {
            $this->newLine();
            $this->line('Rincian koleksi:');
            foreach ($byCollection as $row) {
                $this->line('  - '.str_pad($row->collection_name, 16).' disk '.str_pad($row->disk ?: '?', 8).' : '.$row->jumlah);
            }
        }

        // Gambar yang seharusnya publik tetapi tersimpan di disk privat tidak
        // akan pernah punya URL — ini penyebab "terupload tapi tidak tampil".
        $misplaced = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()
            ->whereIn('collection_name', self::PUBLIC_COLLECTIONS)
            ->where('disk', '!=', 'public')
            ->get();

        if ($misplaced->isNotEmpty()) {
            $this->newLine();
            $this->line($misplaced->count().' berkas publik tersimpan di disk yang salah:');
            foreach ($misplaced as $media) {
                $this->line('  - '.$media->collection_name.' #'.$media->id.' di disk '.$media->disk);
            }

            // Baris media yatim (model pemiliknya sudah dihapus) tidak dipakai di
            // halaman mana pun, jadi tidak perlu — dan tidak bisa — dipindahkan.
            $orphans = $misplaced->filter(fn ($media) => $media->model === null);
            $movable = $misplaced->reject(fn ($media) => $media->model === null);

            if ($orphans->isNotEmpty()) {
                $this->comment('  '.$orphans->count().' di antaranya yatim (pemiliknya sudah dihapus) — diabaikan, tidak dipakai halaman mana pun.');
            }

            if ($movable->isEmpty()) {
                // Tidak ada yang perlu diperbaiki; jangan laporkan sebagai masalah.
            } elseif ($this->option('fix')) {
                $moved = 0;
                foreach ($movable as $media) {
                    try {
                        $media->move($media->model, $media->collection_name, 'public');
                        $moved++;
                    } catch (\Throwable $e) {
                        $problems[] = 'Gagal memindahkan '.$media->collection_name.' #'.$media->id.': '.$e->getMessage();
                    }
                }
                $this->info($moved.' dari '.$movable->count().' berkas dipindahkan ke disk publik.');
            } else {
                $problems[] = $movable->count().' berkas publik ada di disk privat sehingga tidak punya URL. Jalankan ulang dengan --fix untuk memindahkannya.';
            }
        }

        if (($perDisk['public'] ?? 0) === 0 && $misplaced->isEmpty()) {
            $problems[] = 'Tidak ada satu pun berkas di disk publik — artinya unggahan gambar belum benar-benar tersimpan sebagai media.';
        }
        if ($missing !== []) {
            $problems[] = count($missing).' berkas tercatat tetapi tidak ada di disk: '.implode(', ', array_slice($missing, 0, 5));
        }

        // Contoh URL hanya bermakna untuk media di disk publik.
        $publicRow = $rows->first(fn ($row) => ($row->disk ?: config('media-library.disk_name')) === 'public');
        if ($publicRow) {
            $this->line('Contoh URL        : '.rtrim(config('app.url'), '/').'/storage/'.$publicRow->id.'/'.$publicRow->file_name);
            $this->comment('Buka URL contoh di browser. Bila 404, akses publik ke media memang bermasalah.');
        }

        // Konversi gambar butuh GD/Imagick dengan dukungan webp.
        $webp = function_exists('imagewebp') || (extension_loaded('imagick') && in_array('WEBP', \Imagick::queryFormats('WEBP') ?: [], true));
        $this->line('Dukungan WebP     : '.($webp ? 'ada' : 'TIDAK ADA'));
        if (! $webp) {
            $this->comment('Tanpa WebP, versi kecil gambar gagal dibuat; sistem memakai gambar asli (ukuran lebih besar).');
        }

        if ($problems === []) {
            $this->newLine();
            $this->info('Akses media sehat. Tidak ada masalah ditemukan.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error('Ditemukan '.count($problems).' masalah:');
        foreach ($problems as $problem) {
            $this->error(' - '.$problem);
        }

        return self::FAILURE;
    }
}
