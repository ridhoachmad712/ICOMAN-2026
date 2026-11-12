<?php

namespace App\Services;

use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF sertifikat A4 landscape, dibuat saat diunduh (tidak disimpan), jadi
 * perubahan template di Pengaturan langsung berlaku. QR code mengarah ke
 * halaman verifikasi publik sertifikat ini.
 *
 * Dua mode: desain bawaan (judul, teks pengantar, dan tanda tangan dicetak
 * sistem), atau template custom, di mana desain latar dari panitia sudah
 * memuat semua itu dan sistem hanya mencetak data dinamis pada posisi yang
 * diatur di Pengaturan → Sertifikat (lihat DEFAULT_LAYOUT).
 *
 * QR dibuat dengan chillerlan/php-qrcode, yang sudah terpasang sebagai
 * dependensi Filament; gambar disematkan sebagai data URI karena dompdf tidak
 * mengambil berkas lewat HTTP.
 */
class CertificatePdf
{
    /**
     * Posisi bawaan elemen dinamis pada A4 landscape (297 × 210 mm). Ukuran
     * huruf dalam pt; untuk QR, `size` adalah sisi kotaknya dalam mm.
     */
    public const DEFAULT_LAYOUT = [
        'name' => ['top' => 88, 'left' => 30, 'width' => 237, 'size' => 28, 'align' => 'center', 'color' => '#18315e',
            'font' => 'serif', 'bold' => true, 'italic' => false, 'uppercase' => false, 'visible' => true],
        'affiliation' => ['top' => 104, 'left' => 30, 'width' => 237, 'size' => 12, 'align' => 'center', 'color' => '#3d4b5c',
            'font' => 'sans', 'bold' => false, 'italic' => false, 'uppercase' => false, 'visible' => true],
        'paper_title' => ['top' => 116, 'left' => 45, 'width' => 207, 'size' => 13, 'align' => 'center', 'color' => '#1f2d3d',
            'font' => 'serif', 'bold' => false, 'italic' => true, 'uppercase' => false, 'visible' => true, 'quotes' => true],
        'number' => ['top' => 190, 'left' => 30, 'width' => 237, 'size' => 9, 'align' => 'center', 'color' => '#3d4b5c',
            'font' => 'sans', 'bold' => false, 'italic' => false, 'uppercase' => false, 'visible' => true, 'prefix' => 'No. '],
        'qr' => ['top' => 160, 'left' => 250, 'size' => 25, 'visible' => true],
    ];

    /** Pilihan huruf. Tiga yang pertama tersedia di dompdf; "custom" memakai TTF unggahan panitia. */
    public const FONTS = [
        'sans' => 'Sans-serif (DejaVu Sans)',
        'serif' => 'Serif (DejaVu Serif)',
        'mono' => 'Monospace (DejaVu Sans Mono)',
        'custom' => 'Huruf unggahan (TTF)',
    ];

    /** Pengaturan ya/tidak per elemen; disimpan sebagai '1'/'0' dari form. */
    public const SWITCHES = ['bold', 'italic', 'uppercase', 'visible', 'quotes'];

    public const ELEMENTS = [
        'name' => 'Nama penerima',
        'affiliation' => 'Afiliasi',
        'paper_title' => 'Judul paper (presenter)',
        'number' => 'Nomor sertifikat',
        'qr' => 'QR code',
    ];

    public function download(Certificate $certificate): Response
    {
        return $this->pdf($certificate)->download($certificate->fileName());
    }

    public function stream(Certificate $certificate): Response
    {
        return $this->pdf($certificate)->stream($certificate->fileName());
    }

    /**
     * Pratinjau dengan data contoh, dari pengaturan yang sudah disimpan.
     * Tidak menyimpan apa pun; QR-nya mengarah ke kode yang tidak terdaftar.
     */
    public function preview(string $type): Response
    {
        $certificate = new Certificate([
            'type' => $type,
            'number' => $type === 'presentation' ? 'ICOMAN2026-001/P1' : 'ICOMAN2026/A/0001',
            'recipient_name' => 'Dr. Nama Penerima, S.E., M.M.',
            'recipient_affiliation' => 'Universitas Contoh, Indonesia',
            'paper_title' => $type === 'presentation'
                ? 'Contoh Judul Paper yang Cukup Panjang untuk Menguji Pemenggalan Baris pada Sertifikat'
                : null,
            'verification_token' => str_repeat('0', 40),
            'issued_at' => now(),
        ]);
        $certificate->setRelation('edition', currentEdition());

        return $this->pdf($certificate)->stream('pratinjau-sertifikat-'.$type.'.pdf');
    }

    public function qrDataUri(Certificate $certificate): string
    {
        $options = new QROptions([
            // Di versi 5.0 jenis keluaran dipilih lewat outputType; tanpa ini
            // hasilnya SVG, yang dirender dompdf kurang andal.
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'eccLevel' => EccLevel::M,
            'scale' => 8,
            'quietzoneSize' => 2,
            'outputBase64' => true,
        ]);

        return (new QRCode($options))->render($certificate->verificationUrl());
    }

    /** @return array<string, array<string, mixed>> posisi akhir per elemen untuk satu jenis sertifikat */
    public function layout(string $type): array
    {
        $saved = (array) (siteSettings()->certificate_layout[$type] ?? []);
        $layout = [];

        foreach (self::DEFAULT_LAYOUT as $element => $defaults) {
            $layout[$element] = array_merge(
                $defaults,
                array_filter((array) ($saved[$element] ?? []), fn ($value) => $value !== null && $value !== ''),
            );

            foreach (self::SWITCHES as $switch) {
                if (array_key_exists($switch, $layout[$element])) {
                    $layout[$element][$switch] = filter_var($layout[$element][$switch], FILTER_VALIDATE_BOOLEAN);
                }
            }

            // Nama penerima selalu dicetak: sertifikat tanpa nama tidak ada gunanya.
            if ($element === 'name') {
                $layout[$element]['visible'] = true;
            }

            if (isset($layout[$element]['font']) && ! array_key_exists($layout[$element]['font'], self::FONTS)) {
                $layout[$element]['font'] = $defaults['font'];
            }
        }

        return $layout;
    }

    /**
     * Membersihkan tata letak kiriman editor visual sebelum disimpan: hanya
     * kunci yang dikenal, angka dalam batas kertas, dan nilai pilihan yang sah.
     *
     * @param  array<string, mixed>  $layout
     * @return array<string, array<string, mixed>>
     */
    public static function sanitizeLayout(array $layout): array
    {
        $clamp = fn ($value, float $min, float $max, float $fallback): float => is_numeric($value)
            ? round(min($max, max($min, (float) $value)), 1)
            : $fallback;
        $clean = [];

        foreach (self::DEFAULT_LAYOUT as $element => $defaults) {
            $given = (array) ($layout[$element] ?? []);
            $item = [
                'top' => $clamp($given['top'] ?? null, 0, 210, $defaults['top']),
                'left' => $clamp($given['left'] ?? null, 0, 297, $defaults['left']),
            ];

            if ($element === 'qr') {
                $item['size'] = $clamp($given['size'] ?? null, 10, 80, $defaults['size']);
            } else {
                $item['width'] = $clamp($given['width'] ?? null, 10, 297, $defaults['width']);
                $item['size'] = $clamp($given['size'] ?? null, 6, 72, $defaults['size']);
                $item['align'] = in_array($given['align'] ?? null, ['left', 'center', 'right'], true) ? $given['align'] : $defaults['align'];
                $item['color'] = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($given['color'] ?? '')) ? strtolower($given['color']) : $defaults['color'];
                $item['font'] = array_key_exists((string) ($given['font'] ?? ''), self::FONTS) ? $given['font'] : $defaults['font'];
            }

            foreach (self::SWITCHES as $switch) {
                if (array_key_exists($switch, $defaults)) {
                    // Disimpan '1'/'0' agar sama dengan isian di Pengaturan.
                    $item[$switch] = filter_var($given[$switch] ?? $defaults[$switch], FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
                }
            }

            if ($element === 'name') {
                $item['visible'] = '1';
            }

            if ($element === 'number') {
                $item['prefix'] = mb_substr(trim(strip_tags((string) ($given['prefix'] ?? $defaults['prefix']))), 0, 30);
            }

            $clean[$element] = $item;
        }

        return $clean;
    }

    /**
     * Huruf unggahan, bila ada: nama keluarga untuk CSS dan path berkasnya.
     * Nama keluarganya memuat sidik berkas, karena dompdf menyimpan ukuran
     * huruf per nama keluarga; tanpa itu huruf pengganti memakai cache lama.
     *
     * @return array{family: string, src: string}|null
     */
    public function customFont(): ?array
    {
        $path = rescue(fn () => siteSettings()->certificate_font, null, false);
        $disk = Storage::disk('local');

        if (blank($path) || ! $disk->exists($path)) {
            return null;
        }

        return [
            'family' => 'certfont'.substr(md5($path.'|'.$disk->lastModified($path)), 0, 10),
            // Path biasa, tanpa file://: bentuk itulah yang dibaca dompdf di Windows dan Linux.
            'src' => str_replace('\\', '/', $disk->path($path)),
        ];
    }

    /** CSS font-family untuk pilihan huruf sebuah elemen. */
    public static function fontFamily(string $font, ?array $customFont): string
    {
        return match ($font) {
            'serif' => 'DejaVu Serif, serif',
            'mono' => 'DejaVu Sans Mono, monospace',
            'custom' => $customFont ? $customFont['family'].', DejaVu Sans, sans-serif' : 'DejaVu Sans, sans-serif',
            default => 'DejaVu Sans, sans-serif',
        };
    }

    private function pdf(Certificate $certificate)
    {
        $certificate->loadMissing('edition');
        $settings = rescue(fn () => siteSettings(), null, false);
        $background = $certificate->type === 'participation'
            ? ($settings?->certificate_background_participation ?: $settings?->certificate_background_presentation)
            : $settings?->certificate_background_presentation;

        $customFont = ($settings?->certificate_custom_layout ?? false) ? $this->customFont() : null;
        if ($customFont) {
            // dompdf menyimpan ukuran huruf unggahan di sini.
            File::ensureDirectoryExists(storage_path('fonts'));
        }

        return Pdf::loadView('pdf.certificate', [
            'customFont' => $customFont,
            'certificate' => $certificate,
            'settings' => $settings,
            'qr' => $this->qrDataUri($certificate),
            'background' => $this->imageDataUri($background),
            'signature' => $this->imageDataUri($settings?->certificate_signature),
            'logo' => $this->imageDataUri($settings?->logo),
            // Mode custom butuh latar; tanpa latar, kertasnya kosong selain data.
            'custom' => (bool) $settings?->certificate_custom_layout && filled($background),
            'layout' => $this->layout($certificate->type),
        ])->setPaper('a4', 'landscape');
    }

    private function imageDataUri(?string $path): ?string
    {
        $disk = Storage::disk('public');

        if (blank($path) || ! $disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path) ?: 'image/png';

        return in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)
            ? 'data:'.$mime.';base64,'.base64_encode($disk->get($path))
            : null;
    }
}
