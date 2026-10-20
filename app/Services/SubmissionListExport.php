<?php

namespace App\Services;

use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/**
 * Daftar paper dalam Excel: pengirim beserta kontaknya, seluruh penulis, judul, afiliasi,
 * sub-tema, dan apakah full paper sudah dikumpulkan. Excel, bukan CSV, karena judul paper memuat koma dan karakter
 * non-ASCII yang sering rusak saat CSV dibuka di Excel berbahasa Indonesia.
 */
class SubmissionListExport
{
    public const HEADINGS = [
        'No', 'Kode', 'Nama', 'Jenis kepesertaan', 'Email', 'No. HP / WhatsApp', 'Semua penulis', 'Judul Paper', 'Afiliasi', 'Sub-tema', 'Status', 'Full paper', 'Tanggal full paper',
    ];

    public function create(Builder $submissions): string
    {
        $path = tempnam(sys_get_temp_dir(), 'paper-');

        if ($path === false) {
            throw new RuntimeException('Berkas sementara tidak dapat dibuat.');
        }

        $options = new Options;
        foreach ([6, 16, 28, 20, 34, 22, 48, 60, 36, 48, 18, 12, 18] as $index => $width) {
            $options->setColumnWidth($width, $index + 1);
        }

        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(self::HEADINGS, (new Style)->setFontBold()));

        $number = 0;
        $submissions->clone()
            ->with(['author', 'topic', 'authors', 'media'])
            ->chunk(200, function ($rows) use ($writer, &$number): void {
                foreach ($rows as $submission) {
                    /** @var Submission $submission */
                    $authors = $submission->authors->sortBy('order');

                    $writer->addRow(Row::fromValues([
                        ++$number,
                        (string) $submission->submission_number,
                        (string) $submission->author?->name,
                        // Kategori tarif pengirim: Mahasiswa S1, Dosen / Umum, atau International.
                        (string) $submission->author?->registrantCategoryLabel(),
                        (string) $submission->author?->email,
                        // Selalu teks, supaya 0 atau + di depan nomor tidak hilang.
                        (string) $submission->author?->phone,
                        $authors
                            ->map(fn ($author) => $author->name.($author->affiliation ? ' ('.$author->affiliation.')' : ''))
                            ->implode('; '),
                        (string) $submission->title,
                        $this->affiliation($submission),
                        (string) $submission->topic?->title,
                        Submission::STATUS_LABELS[$submission->status] ?? (string) $submission->status,
                        $submission->hasFullPaper() ? 'Sudah' : 'Belum',
                        $submission->hasFullPaper()
                            ? (string) ($submission->full_paper_submitted_at ?? $submission->fullPaperMedia()?->created_at)?->format('d M Y H:i')
                            : '',
                    ]));
                }
            });

        $writer->close();

        return $path;
    }

    public function fileName(): string
    {
        return 'daftar-paper-'.now()->format('Ymd-His').'.xlsx';
    }

    /**
     * Afiliasi pengirim dari akunnya; bila kosong, afiliasi yang ia tulis
     * untuk dirinya di daftar penulis paper, lalu corresponding author.
     */
    private function affiliation(Submission $submission): string
    {
        $authors = $submission->authors;

        return (string) ($submission->author?->affiliation
            ?: $authors->firstWhere('email', $submission->author?->email)?->affiliation
            ?: $authors->firstWhere('is_corresponding', true)?->affiliation
            ?: '');
    }
}
