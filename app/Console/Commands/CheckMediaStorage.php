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
            $actual = is_link($link) ? readlink($link) : $link.' (folder biasa)';
            $this->line('public/storage    : '.$actual);

            if (is_link($link) && realpath($actual) !== realpath($target)) {
                $problems[] = 'public/storage menunjuk ke lokasi yang salah: '.$actual;
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

        foreach ($perDisk as $disk => $count) {
            $this->line('Berkas di disk '.str_pad($disk, 8).': '.$count);
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
