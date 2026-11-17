<?php

namespace App\Livewire;

use App\Services\CertificateZipExport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Panel ZIP sertifikat di halaman Sertifikat. Selama ZIP disusun, panel ini
 * terus memanggil work() sehingga penyusunannya berjalan dari browser admin
 * (tanpa queue worker), langkah demi langkah di bawah batas waktu nginx.
 * Komponen terpisah supaya hanya panel kecil ini yang memperbarui diri.
 */
class CertificateZipStatus extends Component
{
    #[On('certificate-zip-started')]
    public function refreshStatus(): void
    {
        // Merender ulang cukup; statusnya dibaca dari cache di render().
    }

    /** Dipanggil berulang oleh panel selama ZIP disusun; tiap panggilan satu langkah. */
    public function work(CertificateZipExport $export): void
    {
        if (auth()->user()?->managesSubmissions()) {
            $export->work(auth()->user());
        }
    }

    public function dismiss(CertificateZipExport $export): void
    {
        $export->discard(auth()->user());
    }

    public function render(CertificateZipExport $export): View
    {
        return view('livewire.certificate-zip-status', [
            'status' => auth()->user() ? $export->status(auth()->user()) : null,
        ]);
    }
}
