<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\CertificatePdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CertificateController extends Controller
{
    /**
     * Halaman verifikasi publik, tujuan QR di sertifikat. Hanya menampilkan
     * yang tercetak di sertifikat itu sendiri: tidak ada email atau nomor HP.
     */
    public function verify(string $token): View
    {
        $certificate = Certificate::with('edition')->where('verification_token', $token)->first();

        return view('certificates.verify', ['certificate' => $certificate]);
    }

    /** Unduhan pemilik: semua sertifikat yang diterbitkan lewat akunnya, kecuali yang dicabut. */
    public function download(Certificate $certificate, CertificatePdf $pdf): Response
    {
        abort_unless($certificate->author_id === Auth::guard('author')->id(), 403);
        abort_if($certificate->isRevoked(), 404);

        return $pdf->download($certificate);
    }

    /** Pratinjau template dengan data contoh, dari pengaturan yang sudah disimpan. */
    public function preview(string $type, CertificatePdf $pdf): Response
    {
        abort_unless(Auth::guard('web')->user()?->managesSubmissions(), 403);
        abort_unless(array_key_exists($type, Certificate::TYPES), 404);

        return $pdf->preview($type);
    }

    /** Huruf unggahan untuk kanvas editor sertifikat; berkasnya tersimpan privat. */
    public function font(): Response
    {
        abort_unless(Auth::guard('web')->user()?->isSuperadmin(), 403);

        $path = siteSettings()->certificate_font;
        abort_if(blank($path) || ! Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => 'font/ttf',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function downloadForAdmin(Certificate $certificate, CertificatePdf $pdf): Response
    {
        abort_unless(Auth::guard('web')->user()?->managesSubmissions(), 403);

        return $pdf->stream($certificate);
    }
}
