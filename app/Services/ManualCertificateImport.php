<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Edition;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Impor massal sertifikat manual dari CSV (kolom: jenis, nama, afiliasi,
 * judul, nomor). Semua baris diperiksa dulu; bila satu saja salah, tidak ada
 * yang diterbitkan, supaya panitia cukup memperbaiki berkasnya lalu
 * mengunggah ulang tanpa memilah mana yang sudah masuk.
 */
class ManualCertificateImport
{
    public const HEADERS = ['jenis', 'nama', 'afiliasi', 'judul', 'nomor'];

    private const TYPES = [
        'presenter' => 'presentation',
        'presentation' => 'presentation',
        'pemakalah' => 'presentation',
        'peserta' => 'participation',
        'peserta seminar' => 'participation',
        'participant' => 'participation',
        'participation' => 'participation',
    ];

    public function __construct(private CertificateIssuer $issuer) {}

    /** Isi templat CSV untuk diunduh panitia. */
    public static function template(): string
    {
        return "\u{FEFF}".implode(';', self::HEADERS)."\n"
            ."presenter;Dr. Budi Hartono, M.M.;Universitas Negeri Makassar;Green Marketing and Consumer Loyalty;\n"
            ."peserta;Sari Wulandari, S.E.;Universitas Hasanuddin;;\n";
    }

    /**
     * Membaca dan memeriksa berkas tanpa menyimpan apa pun.
     *
     * @return array{rows: list<array<string, ?string>>, errors: list<string>}
     */
    public function parse(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\x{FEFF}/u', '', $content) ?? $content;
        // Excel berbahasa Indonesia menyimpan CSV dengan titik koma.
        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $records = [];
        $buffer = '';
        foreach ($lines as $line) {
            // Judul yang dikutip boleh memuat baris baru: gabungkan sampai kutipnya tertutup.
            $buffer = $buffer === '' ? $line : $buffer."\n".$line;
            if (substr_count($buffer, '"') % 2 === 0) {
                $records[] = $buffer;
                $buffer = '';
            }
        }

        $header = array_map(fn ($cell) => strtolower(trim((string) $cell)), str_getcsv((string) array_shift($records), $delimiter, '"', ''));
        $missing = array_diff(['jenis', 'nama'], $header);
        if ($missing !== []) {
            return ['rows' => [], 'errors' => ['Baris judul kolom harus memuat: '.implode(', ', self::HEADERS).'. Unduh templatnya agar formatnya sesuai.']];
        }

        $rows = [];
        $errors = [];
        $numbers = [];
        foreach ($records as $index => $record) {
            if (trim(str_replace([$delimiter, '"'], '', $record)) === '') {
                continue;
            }

            $line = $index + 2;
            $cells = str_getcsv($record, $delimiter, '"', '');
            $value = function (string $column) use ($header, $cells): ?string {
                $position = array_search($column, $header, true);
                $cell = $position === false ? null : trim((string) ($cells[$position] ?? ''));

                return $cell === '' ? null : $cell;
            };

            $type = self::TYPES[strtolower((string) $value('jenis'))] ?? null;
            $row = [
                'type' => $type,
                'recipient_name' => $value('nama'),
                'recipient_affiliation' => $value('afiliasi'),
                'paper_title' => $type === 'presentation' ? $value('judul') : null,
                'number' => $value('nomor'),
            ];

            if ($type === null) {
                $errors[] = "Baris {$line}: jenis harus \"presenter\" atau \"peserta\".";
            }
            if ($row['recipient_name'] === null) {
                $errors[] = "Baris {$line}: nama wajib diisi.";
            }
            if ($type === 'presentation' && $row['paper_title'] === null) {
                $errors[] = "Baris {$line}: judul wajib diisi untuk presenter.";
            }
            foreach (['recipient_name' => 'nama', 'recipient_affiliation' => 'afiliasi', 'number' => 'nomor'] as $field => $label) {
                if (mb_strlen((string) $row[$field]) > 255) {
                    $errors[] = "Baris {$line}: {$label} lebih dari 255 karakter.";
                }
            }
            if ($row['number'] !== null) {
                if (in_array($row['number'], $numbers, true)) {
                    $errors[] = "Baris {$line}: nomor {$row['number']} dipakai lebih dari sekali di berkas.";
                } elseif (Certificate::where('number', $row['number'])->exists()) {
                    $errors[] = "Baris {$line}: nomor {$row['number']} sudah dipakai sertifikat lain.";
                }
                $numbers[] = $row['number'];
            }

            $rows[] = $row;
        }

        if ($rows === [] && $errors === []) {
            $errors[] = 'Berkas tidak berisi data.';
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Menerbitkan semua baris; berhenti tanpa menyimpan apa pun bila ada yang salah.
     *
     * @return array{count: int, errors: list<string>}
     */
    public function import(Edition $edition, string $path, ?User $issuer = null): array
    {
        ['rows' => $rows, 'errors' => $errors] = $this->parse($path);

        if ($errors !== []) {
            return ['count' => 0, 'errors' => $errors];
        }

        DB::transaction(function () use ($rows, $edition, $issuer): void {
            foreach ($rows as $row) {
                $this->issuer->issueManual($edition, $row, $issuer);
            }
        });

        return ['count' => count($rows), 'errors' => []];
    }
}
