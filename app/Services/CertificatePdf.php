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
 * QR dibuat dengan chillerlan/php-qrcode, yang sudah terpasang sebagai
 * dependensi Filament; gambar disematkan sebagai data URI karena dompdf tidak
 * mengambil berkas lewat HTTP.
 */
class CertificatePdf
{
    public function download(Certificate $certificate): Response
    {
        return $this->pdf($certificate)->download($certificate->fileName());
    }

    public function stream(Certificate $certificate): Response
    {
        return $this->pdf($certificate)->stream($certificate->fileName());
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

    private function pdf(Certificate $certificate)
    {
        $certificate->loadMissing('edition');
        $settings = rescue(fn () => siteSettings(), null, false);

        return Pdf::loadView('pdf.certificate', [
            'certificate' => $certificate,
            'settings' => $settings,
            'qr' => $this->qrDataUri($certificate),
            'background' => $this->imageDataUri($settings?->certificate_background),
            'signature' => $this->imageDataUri($settings?->certificate_signature),
            'logo' => $this->imageDataUri($settings?->logo),
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
