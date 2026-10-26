<?php

namespace App\Services;

use App\Models\Author;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/**
 * Data inti peserta (nama, afiliasi, email, nomor HP) dalam berkas Excel.
 *
 * Excel (.xlsx), bukan CSV: CSV membuang angka 0 di depan nomor HP dan sering
 * terbuka sebagai satu kolom pada Excel berbahasa Indonesia yang memakai titik
 * koma sebagai pemisah. OpenSpout sudah terpasang bersama Filament, jadi tidak
 * ada paket baru.
 */
class ParticipantContactExport
{
    public const HEADINGS = [
        'No', 'Nama', 'Afiliasi', 'Email', 'No. HP / WhatsApp', 'Negara', 'Jalur', 'Jenis kepesertaan',
        'Kode paper', 'Judul paper', 'Progres saat ini', 'Status pembayaran', 'No. invoice', 'Total tagihan', 'Tanggal lunas',
    ];

    private const PATHS = [
        'presenter' => 'Presenter',
        'participant' => 'Peserta seminar',
        'cohost' => 'Co-host',
    ];

    /** Menulis berkas sementara dari query akun author dan mengembalikan path-nya. */
    public function create(Builder $authors): string
    {
        $editionId = currentEdition()?->id;
        $progress = app(AuthorProgress::class);
        $path = tempnam(sys_get_temp_dir(), 'peserta-');

        if ($path === false) {
            throw new RuntimeException('Berkas sementara tidak dapat dibuat.');
        }

        $options = new Options;
        foreach ([6, 32, 40, 34, 22, 18, 18, 16, 18, 60, 24, 18, 14, 16, 18] as $index => $width) {
            $options->setColumnWidth($width, $index + 1);
        }

        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(self::HEADINGS, (new Style)->setFontBold()));

        $number = 0;
        $authors->clone()
            ->with([
                'registrations' => fn ($query) => $query->when($editionId, fn ($query) => $query->where('edition_id', $editionId)),
                'submissions' => fn ($query) => $query->when($editionId, fn ($query) => $query->where('edition_id', $editionId)),
            ])
            ->chunk(200, function ($rows) use ($writer, $progress, &$number): void {
                foreach ($rows as $author) {
                    /** @var Author $author */
                    // Invoice dan tahap yang sama dengan yang tampil di tabel Progres Peserta.
                    $snapshot = $progress->snapshot($author);
                    $registration = $snapshot['registration'];

                    $writer->addRow(Row::fromValues([
                        ++$number,
                        (string) $author->name,
                        (string) $author->affiliation,
                        (string) $author->email,
                        // Selalu teks, supaya 0 atau + di depan nomor tidak hilang.
                        (string) $author->phone,
                        (string) countryName($author->country),
                        self::PATHS[$author->participation_type] ?? '',
                        $author->registrantCategoryLabel(),
                        // Paper edisi aktif yang sama dengan kolom Paper di tabel; kosong untuk peserta seminar.
                        (string) $snapshot['paper']?->submission_number,
                        (string) $snapshot['paper']?->title,
                        $snapshot['label'],
                        AuthorProgress::paymentLabel($registration),
                        $registration ? '#'.str_pad((string) $registration->id, 5, '0', STR_PAD_LEFT) : '',
                        // Angka, bukan teks, supaya bisa dijumlah di Excel.
                        $registration ? (float) $registration->amount : '',
                        (string) $registration?->paid_at?->format('d M Y H:i'),
                    ]));
                }
            });

        $writer->close();

        return $path;
    }

    public function fileName(): string
    {
        return 'data-peserta-'.now()->format('Ymd-His').'.xlsx';
    }
}
