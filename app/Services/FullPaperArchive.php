<?php

namespace App\Services;

use App\Models\Submission;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Full paper banyak paper sekaligus dalam satu ZIP. Berkas aslinya dikemas
 * apa adanya (PDF/DOC/DOCX tidak bisa digabung menjadi satu dokumen dengan
 * rapi), satu berkas per paper, memakai versi terakhir yang diunggah author.
 */
class FullPaperArchive
{
    /**
     * @param  iterable<Submission>  $submissions
     * @return array{path: string, count: int}
     */
    public function create(iterable $submissions): array
    {
        $path = tempnam(sys_get_temp_dir(), 'full-papers-');
        $zip = new ZipArchive;

        if ($path === false || $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Berkas ZIP sementara tidak dapat dibuat.');
        }

        $count = 0;
        $usedNames = [];

        foreach ($submissions as $submission) {
            $media = $submission->fullPaperMedia();

            if (! $media || ! is_file($media->getPath())) {
                continue;
            }

            $name = $this->entryName($submission, (string) $media->file_name);
            // Dua paper dengan kode dan judul sama tidak mungkin, tapi nama
            // ganda tetap dijaga agar tidak ada berkas yang tertimpa di ZIP.
            $base = $name;
            for ($copy = 2; isset($usedNames[strtolower($name)]); $copy++) {
                $name = preg_replace('/(\.[^.]+)?$/', ' ('.$copy.')$1', $base, 1);
            }
            $usedNames[strtolower($name)] = true;

            $zip->addFile($media->getPath(), $name);
            $count++;
        }

        if ($count === 0) {
            // ZipArchive tidak menulis arsip kosong; pemanggil yang memberi tahu panitia.
            $zip->close();
            @unlink($path);

            return ['path' => '', 'count' => 0];
        }

        $zip->close();

        return ['path' => $path, 'count' => $count];
    }

    public function fileName(): string
    {
        return 'full-paper-'.now()->format('Ymd-His').'.zip';
    }

    /** "ICOMAN2026-001 - Judul paper yang dipendekkan.pdf" */
    private function entryName(Submission $submission, string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) ?: 'pdf';
        $title = Str::limit(trim(preg_replace('/[\\\\\/:*?"<>|\r\n\t]+/', ' ', (string) $submission->title)), 80, '');

        return trim($submission->submission_number.' - '.$title).'.'.$extension;
    }
}
