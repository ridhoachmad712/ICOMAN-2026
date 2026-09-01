<?php

namespace App\Services;

use App\Models\Submission;

class ExtendedAbstractDocument
{
    /**
     * Bagian dokumen abstract. Abstract kini teks polos (tanpa lampiran gambar),
     * sehingga parameter $embedImages dipertahankan untuk kompatibilitas pemanggil
     * tetapi tidak lagi melakukan embedding.
     *
     * @return array<string, array{label: string, html: string, text: string}>
     */
    public function sections(Submission $submission, bool $embedImages = false): array
    {
        return $submission->extendedAbstractSections();
    }
}
