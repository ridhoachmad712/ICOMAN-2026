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
        'No', 'Nama', 'Afiliasi', 'Email', 'No. HP / WhatsApp', 'Negara', 'Jalur', 'Kategori', 'Status pembayaran',
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
        $path = tempnam(sys_get_temp_dir(), 'peserta-');

        if ($path === false) {
            throw new RuntimeException('Berkas sementara tidak dapat dibuat.');
        }

        $options = new Options;
        foreach ([6, 32, 40, 34, 22, 18, 18, 16, 22] as $index => $width) {
            $options->setColumnWidth($width, $index + 1);
        }

        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(self::HEADINGS, (new Style)->setFontBold()));

        $number = 0;
        $authors->clone()
            ->with(['registrations' => fn ($query) => $query
                ->when($editionId, fn ($query) => $query->where('edition_id', $editionId))])
            ->chunk(200, function ($rows) use ($writer, &$number): void {
                foreach ($rows as $author) {
                    /** @var Author $author */
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
                        $this->paymentStatus($author),
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

    private function paymentStatus(Author $author): string
    {
        $statuses = $author->registrations->pluck('status');

        return match (true) {
            $statuses->contains('paid') => 'Lunas',
            $statuses->contains('pending_verification') => 'Menunggu verifikasi',
            $statuses->isNotEmpty() => 'Belum lunas',
            default => 'Belum ada invoice',
        };
    }
}
