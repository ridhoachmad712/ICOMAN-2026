<?php

namespace App\Services;

use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
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
        'name' => ['top' => 88, 'left' => 30, 'width' => 237, 'size' => 28, 'align' => 'center', 'color' => '#18315e'],
        'affiliation' => ['top' => 104, 'left' => 30, 'width' => 237, 'size' => 12, 'align' => 'center', 'color' => '#3d4b5c'],
        'paper_title' => ['top' => 116, 'left' => 45, 'width' => 207, 'size' => 13, 'align' => 'center', 'color' => '#1f2d3d'],
        'number' => ['top' => 190, 'left' => 30, 'width' => 237, 'size' => 9, 'align' => 'center', 'color' => '#3d4b5c'],
        'qr' => ['top' => 160, 'left' => 250, 'size' => 25],
    ];

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
        }

        return $layout;
    }

    private function pdf(Certificate $certificate)
    {
        $certificate->loadMissing('edition');
        $settings = rescue(fn () => siteSettings(), null, false);
        $background = $certificate->type === 'participation'
            ? ($settings?->certificate_background_participation ?: $settings?->certificate_background_presentation)
            : $settings?->certificate_background_presentation;

        return Pdf::loadView('pdf.certificate', [
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
