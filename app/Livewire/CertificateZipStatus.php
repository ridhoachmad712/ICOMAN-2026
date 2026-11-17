<?php

namespace App\Livewire;

use App\Services\CertificateZipExport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Kemajuan ZIP sertifikat yang sedang disusun antrean. Komponen terpisah
 * supaya hanya panel kecil ini yang memperbarui diri tiap beberapa detik,
 * bukan seluruh tabel sertifikat.
 */
class CertificateZipStatus extends Component
{
    #[On('certificate-zip-started')]
    public function refreshStatus(): void
    {
        // Merender ulang cukup; statusnya dibaca dari cache di render().
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
