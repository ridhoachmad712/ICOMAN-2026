<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Edition;
use App\Models\Registration;
use App\Models\Submission;

/** Ringkasan read-only untuk memantau semua akun pada edisi aktif di admin. */
class AuthorProgress
{
    /** @return array{label: string, detail: string, color: string, percent: int, paper: ?Submission, registration: ?Registration} */
    public function snapshot(Author $author, ?Edition $edition = null): array
    {
        $edition ??= currentEdition();

        if (! $edition) {
            return $this->stage('Belum ada edisi aktif', 'Aktifkan edisi untuk melihat progres.', 'gray', 0);
        }

        $paper = $author->submissions
            ->where('edition_id', $edition->id)
            ->sortByDesc('id')
            ->first();
        $registrations = $author->registrations
            ->where('edition_id', $edition->id)
            ->sortByDesc('id');
        $registration = $registrations->firstWhere('status', 'paid')
            ?? $registrations->first(fn (Registration $item): bool => $item->status !== 'failed')
            ?? $registrations->first();

        if (! $author->participation_type) {
            return $this->stage('Lengkapi profil', 'Menunggu peserta memilih jalur partisipasi.', 'gray', 10, $paper, $registration);
        }

        if ($author->isCoHost()) {
            return $this->coHostStage($author, $edition, $registration);
        }

        if ($author->isParticipant()) {
            return $this->participantStage($registration);
        }

        return $this->presenterStage($paper, $registration);
    }

    /** @return array{label: string, detail: string, color: string, percent: int, paper: ?Submission, registration: ?Registration} */
    private function participantStage(?Registration $registration): array
    {
        if (! $registration) {
            return $this->stage('Belum ada invoice', 'Peserta perlu melanjutkan ke pembayaran.', 'gray', 25);
        }

        return match ($registration->status) {
            'paid' => $this->stage('Siap hadir', 'Pembayaran lunas; akses seminar sudah aktif.', 'success', 100, registration: $registration),
            'pending_verification' => $this->stage('Verifikasi pembayaran', 'Menunggu pemeriksaan panitia.', 'info', 75, registration: $registration),
            'failed' => $this->stage('Pembayaran gagal', 'Peserta perlu mencoba pembayaran lagi.', 'danger', 50, registration: $registration),
            default => $this->stage('Menunggu pembayaran', 'Menunggu tindakan peserta.', 'warning', 50, registration: $registration),
        };
    }

    /** @return array{label: string, detail: string, color: string, percent: int, paper: ?Submission, registration: ?Registration} */
    private function presenterStage(?Submission $paper, ?Registration $registration): array
    {
        if (! $paper) {
            return $this->stage('Belum mulai paper', 'Menunggu peserta mengisi abstract.', 'gray', 20, registration: $registration);
        }

        $stage = fn (string $label, string $detail, string $color, int $percent): array => $this->stage($label, $detail, $color, $percent, $paper, $registration);

        if ($paper->status === 'extended_abstract_draft') {
            return $stage('Draft abstract', 'Menunggu peserta mengirim ke reviewer.', 'gray', 30);
        }

        if ($paper->status === 'revision_required') {
            return $stage('Perlu revisi', 'Menunggu revisi dan kirim ulang dari peserta.', 'warning', 45);
        }

        if ($paper->status === 'rejected') {
            return $stage('Tidak lolos', 'Keputusan review sudah tersedia.', 'danger', 60);
        }

        if (in_array($paper->status, ['extended_abstract_submitted', 'extended_abstract_under_review'], true)) {
            $assignments = $paper->reviewAssignments;

            if ($assignments->isEmpty()) {
                return $stage('Perlu reviewer', 'Menunggu panitia menugaskan reviewer.', 'warning', 50);
            }

            if ($assignments->every(fn ($assignment): bool => $assignment->status === 'completed')) {
                return $stage('Menunggu keputusan', 'Penilaian selesai; menunggu keputusan panitia.', 'warning', 65);
            }

            return $stage('Sedang direview', 'Menunggu penilaian reviewer.', 'info', 60);
        }

        if ($paper->status !== 'accepted') {
            return $stage('Periksa paper', 'Lihat status paper pada detail submission.', 'gray', 50);
        }

        if (! $paper->isLoaIssued()) {
            return $stage('Menunggu LOA', 'Abstract accepted; menunggu penerbitan LOA.', 'info', 70);
        }

        if (! $registration) {
            return $stage('Perlu pembayaran', 'LOA terbit; peserta perlu membuat invoice dan membayar.', 'warning', 75);
        }

        if ($registration->status === 'pending_verification') {
            return $stage('Verifikasi pembayaran', 'Menunggu pemeriksaan panitia.', 'info', 85);
        }

        if ($registration->status === 'failed') {
            return $stage('Pembayaran gagal', 'Peserta perlu mencoba pembayaran lagi.', 'danger', 75);
        }

        if ($registration->status !== 'paid') {
            return $stage('Menunggu pembayaran', 'Menunggu pembayaran peserta.', 'warning', 80);
        }

        if (! $paper->hasFullPaper()) {
            return $stage('Kirim full paper', 'Pembayaran lunas; menunggu naskah lengkap peserta.', 'warning', 90);
        }

        return $stage('Selesai', 'Paper dan pembayaran sudah lengkap.', 'success', 100);
    }

    /** @return array{label: string, detail: string, color: string, percent: int, paper: ?Submission, registration: ?Registration} */
    private function coHostStage(Author $author, Edition $edition, ?Registration $registration): array
    {
        $coHost = $author->coHost;

        if (! $coHost || $coHost->edition_id !== $edition->id) {
            return $this->stage('Belum mengajukan', 'Menunggu pengajuan co-host.', 'gray', 20, registration: $registration);
        }

        if ($coHost->status === 'rejected') {
            return $this->stage('Pengajuan ditolak', 'Keputusan panitia sudah tersedia.', 'danger', 50, registration: $registration);
        }

        if ($coHost->status !== 'approved') {
            return $this->stage('Menunggu persetujuan', 'Pengajuan sedang ditinjau panitia.', 'info', 50, registration: $registration);
        }

        if (! $registration) {
            return $this->stage('Menunggu invoice', 'Pengajuan disetujui; invoice belum tersedia.', 'warning', 70);
        }

        if ($registration->status === 'paid') {
            return $this->stage('Kemitraan aktif', 'Pembayaran co-host sudah lunas.', 'success', 100, registration: $registration);
        }

        if ($registration->status === 'pending_verification') {
            return $this->stage('Verifikasi pembayaran', 'Menunggu pemeriksaan panitia.', 'info', 85, registration: $registration);
        }

        if ($registration->status === 'failed') {
            return $this->stage('Pembayaran gagal', 'Co-host perlu mencoba pembayaran lagi.', 'danger', 80, registration: $registration);
        }

        return $this->stage('Menunggu pembayaran', 'Pengajuan disetujui; invoice belum lunas.', 'warning', 80, registration: $registration);
    }

    /** @return array{label: string, detail: string, color: string, percent: int, paper: ?Submission, registration: ?Registration} */
    private function stage(string $label, string $detail, string $color, int $percent, ?Submission $paper = null, ?Registration $registration = null): array
    {
        return compact('label', 'detail', 'color', 'percent', 'paper', 'registration');
    }
}
