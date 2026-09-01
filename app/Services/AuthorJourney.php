<?php

namespace App\Services;

use App\Filament\Author\Pages\AuthorProfile;
use App\Filament\Author\Resources\Papers\PaperResource;
use App\Filament\Author\Resources\Registrations\RegistrationResource;
use App\Models\Author;
use App\Models\Registration;
use App\Models\Submission;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AuthorJourney
{
    public function nextAction(Author $author, Collection $submissions, Collection $registrations): array
    {
        if (! $author->participation_type) {
            return $this->action(
                'profile', 'Lengkapi jalur Anda', 'Complete your participation path',
                'Pilih apakah Anda mengikuti konferensi sebagai pemakalah atau peserta seminar.',
                'Choose whether you are joining as a presenter or seminar participant.',
                AuthorProfile::getUrl(panel: 'author'), 'Lengkapi profil', 'Complete profile', 10
            );
        }

        if ($author->isParticipant()) {
            return $this->participantAction($registrations);
        }

        return $this->presenterAction($submissions, $registrations);
    }

    private function participantAction(Collection $registrations): array
    {
        $registration = $registrations->first(fn (Registration $item) => $item->status !== 'failed');

        if (! $registration) {
            return $this->action(
                'registration', 'Daftar sebagai peserta seminar', 'Register as a seminar participant',
                'Pilih paket peserta, periksa biayanya, lalu tentukan metode pembayaran.',
                'Choose an attendee package, review the fee, then select a payment method.',
                RegistrationResource::getUrl('create', panel: 'author'), 'Mulai registrasi', 'Start registration', 25
            );
        }

        if ($registration->status === 'paid') {
            return $this->action(
                'complete', 'Registrasi Anda sudah selesai', 'Your registration is complete',
                'Pembayaran telah terverifikasi. Informasi akses acara akan tersedia menjelang pelaksanaan.',
                'Your payment is verified. Event access information will be available closer to the event.',
                RegistrationResource::getUrl('view', ['record' => $registration], panel: 'author'), 'Lihat registrasi', 'View registration', 100, 'complete'
            );
        }

        if ($registration->status === 'pending_verification') {
            return $this->action(
                'waiting', 'Bukti pembayaran sedang diverifikasi', 'Your payment proof is being verified',
                'Tidak ada tindakan tambahan saat ini. Anda dapat memantau hasil verifikasi pada detail registrasi.',
                'No further action is needed now. Track the verification result on the registration detail page.',
                RegistrationResource::getUrl('view', ['record' => $registration], panel: 'author'), 'Lihat status', 'View status', 75, 'committee'
            );
        }

        return $this->action(
            'payment', 'Selesaikan pembayaran', 'Complete your payment',
            'Registrasi sudah dibuat, tetapi pembayaran belum selesai.',
            'Your registration was created, but payment is not complete.',
            RegistrationResource::getUrl('view', ['record' => $registration], panel: 'author'), 'Lanjutkan pembayaran', 'Continue payment', 50
        );
    }

    private function presenterAction(Collection $submissions, Collection $registrations): array
    {
        $accepted = $submissions->firstWhere('status', 'accepted');
        if ($accepted) {
            $paidRegistration = $registrations->contains(fn (Registration $item) => $item->status === 'paid');
            if ($paidRegistration) {
                return $this->paperAction(
                    $accepted, 'complete', 'Registrasi presenter selesai', 'Your presenter registration is complete',
                    'Extended abstract accepted dan pembayaran telah terverifikasi. Slot presentasi Anda terkunci.',
                    'Your extended abstract is accepted and payment is verified. Your presentation slot is secured.',
                    'Lihat registrasi', 'View registration', 100, 'complete'
                );
            }

            $pendingRegistration = $registrations->first(fn (Registration $item) => in_array($item->status, ['pending', 'pending_verification'], true));
            if ($pendingRegistration) {
                $waiting = $pendingRegistration->status === 'pending_verification';

                return $this->action(
                    'payment',
                    $waiting ? 'Bukti pembayaran sedang diverifikasi' : 'Selesaikan pembayaran',
                    $waiting ? 'Your payment proof is being verified' : 'Complete your payment',
                    $waiting
                        ? 'Extended abstract Anda accepted. Panitia sedang memverifikasi bukti pembayaran registrasi presenter.'
                        : 'Extended abstract Anda accepted. Selesaikan pembayaran untuk mengunci slot presentasi.',
                    $waiting
                        ? 'Your extended abstract is accepted. The committee is verifying your presenter registration payment.'
                        : 'Your extended abstract is accepted. Complete the payment to secure your presentation slot.',
                    RegistrationResource::getUrl('view', ['record' => $pendingRegistration], panel: 'author'),
                    $waiting ? 'Lihat status' : 'Lanjutkan pembayaran',
                    $waiting ? 'View status' : 'Continue payment',
                    $waiting ? 90 : 75,
                    $waiting ? 'committee' : 'author'
                );
            }

            return $this->action(
                'payment', 'Bayar registrasi presenter', 'Pay your presenter registration',
                'Extended abstract Anda dinyatakan accepted. Lakukan pembayaran untuk mengunci slot presentasi dan akses seminar.',
                'Your extended abstract is accepted. Complete the payment to secure your presentation slot and seminar access.',
                RegistrationResource::getUrl('create', ['submission' => $accepted->id], panel: 'author'),
                'Bayar sekarang', 'Pay now', 75
            );
        }

        $extendedReview = $submissions->first(fn (Submission $item) => in_array($item->status, ['extended_abstract_submitted', 'extended_abstract_under_review'], true));
        if ($extendedReview) {
            return $this->paperAction(
                $extendedReview, 'verification', 'Menunggu verifikasi reviewer', 'Awaiting reviewer verification',
                'Extended abstract sudah dikirim. Tidak ada tindakan tambahan sampai reviewer menyelesaikan penilaian.',
                'Your extended abstract has been submitted. No further action is needed until the reviewer completes the assessment.',
                'Lihat status', 'View status', 75, 'reviewer'
            );
        }

        $revision = $submissions->firstWhere('status', 'revision_required');
        if ($revision) {
            return $this->action(
                'revision', 'Perbaiki extended abstract', 'Revise your extended abstract',
                'Reviewer meminta revisi. Buka detail untuk membaca catatan reviewer, perbaiki kelima bagian, lalu kirim ulang.',
                'The reviewers requested changes. Open the details to read the reviewer comments, revise all five sections, then resubmit.',
                PaperResource::getUrl('extended-abstract', ['record' => $revision], panel: 'author'),
                'Perbaiki sekarang', 'Revise now', 45
            );
        }

        if ($submissions->isEmpty()) {
            return $this->action(
                'submission', 'Mulai extended abstract', 'Start your extended abstract',
                'Lengkapi data paper, lalu tulis Abstract, Introduction, Method, Results and Discussion, dan Conclusion.',
                'Complete the paper details, then write the Abstract, Introduction, Method, Results and Discussion, and Conclusion.',
                PaperResource::getUrl('create', panel: 'author'), 'Mulai menulis', 'Start writing', 25
            );
        }

        $existing = $submissions->first();

        if (in_array($existing->status, ['extended_abstract_draft', 'abstract_submitted', 'abstract_approved'], true)) {
            return $this->action(
                'extended', 'Lanjutkan extended abstract', 'Continue your extended abstract',
                'Lengkapi lima bagian naskah. Anda dapat menyimpan draft dan memeriksa PDF sebelum mengirim ke reviewer.',
                'Complete all five manuscript sections. You can save a draft and check the PDF before submitting it to the reviewer.',
                PaperResource::getUrl('extended-abstract', ['record' => $existing], panel: 'author'),
                $existing->extended_abstract_draft_saved_at ? 'Lanjutkan draft' : 'Mulai menulis',
                $existing->extended_abstract_draft_saved_at ? 'Continue draft' : 'Start writing',
                25
            );
        }

        return $this->paperAction(
            $existing, 'closed', 'Lihat hasil submission', 'View your submission result',
            'Setiap akun hanya dapat mengirim satu paper pada edisi ini. Buka detail untuk melihat hasil reviewer.',
            'Each account may submit one paper in this edition. Open the details to view the reviewer result.',
            'Lihat hasil', 'View result', 75
        );
    }

    public function timeline(Author $author, Collection $submissions, Collection $registrations): array
    {
        if ($author->isParticipant()) {
            return $this->participantTimeline($author, $registrations);
        }

        return $this->presenterTimeline($author, $submissions, $registrations);
    }

    public function shouldShowPayments(Author $author, Collection $submissions, Collection $registrations): bool
    {
        if ($author->isParticipant() || $registrations->isNotEmpty()) {
            return true;
        }

        // Presenter melihat ringkasan pembayaran setelah papernya accepted.
        return $author->isPresenter() && $submissions->contains(fn (Submission $item) => $item->status === 'accepted');
    }

    public function recentUpdates(Collection $submissions, Collection $registrations): array
    {
        $id = app()->getLocale() === 'id';
        $updates = collect();

        foreach ($submissions as $submission) {
            $submission->loadMissing('reviewAssignments.review');
            $route = PaperResource::getUrl('view', ['record' => $submission], panel: 'author');
            $extendedReviewDate = $this->phaseDate($submission, 'extended_abstract');

            if ($submission->status === 'accepted') {
                $updates->push($this->update(
                    $id ? 'Paper dinyatakan accepted' : 'Paper accepted',
                    $id ? 'Letter of Acceptance sudah tersedia pada detail paper.' : 'Your Letter of Acceptance is available on the paper detail page.',
                    $extendedReviewDate ?? $submission->updated_at,
                    'success', 'heroicon-o-check-circle', $route
                ));
            } elseif ($submission->status === 'rejected') {
                $updates->push($this->update(
                    $id ? 'Hasil review tersedia' : 'Review result available',
                    $id ? 'Paper belum lolos. Buka detail untuk membaca catatan reviewer.' : 'The paper was not approved. Open the details to read reviewer feedback.',
                    $extendedReviewDate ?? $submission->updated_at,
                    'danger', 'heroicon-o-chat-bubble-left-right', $route
                ));
            } elseif ($submission->status === 'revision_required') {
                $updates->push($this->update(
                    $id ? 'Revisi diminta reviewer' : 'Revision requested',
                    $id ? 'Buka detail untuk membaca catatan reviewer, perbaiki naskah, lalu kirim ulang.' : 'Open the details to read reviewer comments, revise the manuscript, then resubmit.',
                    $extendedReviewDate ?? $submission->updated_at,
                    'warning', 'heroicon-o-arrow-uturn-left', $route
                ));
            } elseif (in_array($submission->status, ['extended_abstract_submitted', 'extended_abstract_under_review'], true)) {
                $updates->push($this->update(
                    $id ? 'Extended abstract berhasil dikirim' : 'Extended abstract submitted',
                    $id ? 'Reviewer sedang melakukan verifikasi. Tidak ada tindakan tambahan saat ini.' : 'A reviewer is verifying it. No further action is needed now.',
                    $submission->extended_abstract_submitted_at ?? $submission->updated_at,
                    'info', 'heroicon-o-document-check', $route
                ));
            } elseif (in_array($submission->status, ['extended_abstract_draft', 'abstract_submitted', 'abstract_approved'], true)) {
                $updates->push($this->update(
                    $id ? 'Draft extended abstract tersedia' : 'Extended abstract draft available',
                    $id ? 'Lanjutkan penulisan lima bagian sebelum mengirimkannya ke reviewer.' : 'Continue writing all five sections before submitting them to the reviewer.',
                    $submission->extended_abstract_draft_saved_at ?? $submission->updated_at,
                    'gray', 'heroicon-o-pencil-square', $route
                ));
            } else {
                $updates->push($this->update(
                    $id ? 'Paper diperbarui' : 'Paper updated',
                    $id ? 'Buka detail paper untuk melihat status terbaru.' : 'Open the paper details to see its latest status.',
                    $submission->submitted_at,
                    'gray', 'heroicon-o-document-text', $route
                ));
            }
        }

        foreach ($registrations as $registration) {
            if (! in_array($registration->status, ['paid', 'pending_verification'], true)) {
                continue;
            }

            $route = RegistrationResource::getUrl('view', ['record' => $registration], panel: 'author');
            $updates->push($this->update(
                $registration->status === 'paid'
                    ? ($id ? 'Pembayaran terverifikasi' : 'Payment verified')
                    : ($id ? 'Bukti pembayaran diterima' : 'Payment proof received'),
                $registration->status === 'paid'
                    ? ($id ? 'Pembayaran dinyatakan lunas dan akses seminar sudah termasuk.' : 'Your payment is complete and seminar access is included.')
                    : ($id ? 'Panitia sedang memverifikasi bukti pembayaran Anda.' : 'The committee is verifying your payment proof.'),
                $registration->paid_at ?? $registration->updated_at,
                $registration->status === 'paid' ? 'success' : 'info',
                $registration->status === 'paid' ? 'heroicon-o-credit-card' : 'heroicon-o-clock',
                $route
            ));
        }

        return $updates
            ->filter(fn (array $update) => $update['date'])
            ->sortByDesc(fn (array $update) => $update['date']->getTimestamp())
            ->take(4)
            ->values()
            ->all();
    }

    private function participantTimeline(Author $author, Collection $registrations): array
    {
        $registration = $registrations->first(fn (Registration $item) => $item->status !== 'failed');
        $paid = $registration?->status === 'paid';
        $waiting = $registration?->status === 'pending_verification';

        return [
            $this->step(1, 'Bikin akun', 'Create account', 'complete', $author->created_at),
            $this->step(2, 'Pilih registrasi', 'Choose registration', $registration ? 'complete' : 'current', $registration?->created_at, $registration ? null : 'author'),
            $this->step(3, 'Pembayaran', 'Payment', $paid ? 'complete' : ($registration ? 'current' : 'upcoming'), $registration?->paid_at, $waiting ? 'committee' : ($registration ? 'author' : null)),
        ];
    }

    private function presenterTimeline(Author $author, Collection $submissions, Collection $registrations): array
    {
        /** @var Submission|null $submission */
        $submission = $submissions->first();
        $extendedSubmitted = $submission && (
            $submission->extended_abstract_submitted_at
            || in_array($submission->status, ['extended_abstract_submitted', 'extended_abstract_under_review', 'accepted'], true)
        );
        $accepted = $submission?->status === 'accepted';
        $rejected = $submission?->status === 'rejected';
        $needsRevision = $submission?->status === 'revision_required';
        // Saat revisi, bola kembali ke author: langkah input aktif lagi.
        $inputDone = $extendedSubmitted && ! $needsRevision;

        if ($submission) {
            $submission->loadMissing('reviewAssignments.review');
        }

        $paidRegistration = $registrations->firstWhere('status', 'paid');
        $pendingRegistration = $registrations->first(fn (Registration $item) => in_array($item->status, ['pending', 'pending_verification'], true));

        return [
            $this->step(1, 'Bikin akun', 'Create account', 'complete', $author->created_at),
            $this->step(2, 'Input extended abstract', 'Enter extended abstract', $inputDone ? 'complete' : 'current', $submission?->extended_abstract_submitted_at, ! $inputDone ? 'author' : null),
            $this->step(3, 'Verifikasi reviewer', 'Reviewer verification', $accepted ? 'complete' : ($rejected ? 'failed' : ($inputDone ? 'current' : 'upcoming')), $accepted || $rejected ? $this->phaseDate($submission, 'extended_abstract') : null, $inputDone && ! $accepted && ! $rejected ? 'reviewer' : null),
            $this->step(4, 'Accepted', 'Accepted', $accepted ? 'complete' : ($rejected ? 'failed' : 'upcoming'), $accepted ? ($this->phaseDate($submission, 'extended_abstract') ?? $submission->updated_at) : null),
            $this->step(5, 'Pembayaran', 'Payment', $paidRegistration ? 'complete' : ($accepted ? 'current' : 'upcoming'), $paidRegistration?->paid_at, $accepted && ! $paidRegistration ? ($pendingRegistration?->status === 'pending_verification' ? 'committee' : 'author') : null),
        ];
    }

    private function step(int $number, string $labelId, string $labelEn, string $state, ?CarbonInterface $date = null, ?string $actor = null): array
    {
        $id = app()->getLocale() === 'id';

        return [
            'number' => $number,
            'label' => $id ? $labelId : $labelEn,
            'state' => $state,
            'date' => $date,
            'actor' => $actor ? $this->actorMeta($actor) : null,
        ];
    }

    private function update(string $title, string $description, ?CarbonInterface $date, string $color, string $icon, string $route): array
    {
        return compact('title', 'description', 'date', 'color', 'icon', 'route');
    }

    private function phaseDate(?Submission $submission, string $phase): ?CarbonInterface
    {
        if (! $submission) {
            return null;
        }

        $assignment = $submission->reviewAssignments
            ->where('phase', $phase)
            ->sortByDesc(fn ($item) => $item->review?->submitted_at?->getTimestamp() ?? $item->assigned_at?->getTimestamp() ?? 0)
            ->first();

        return $assignment?->review?->submitted_at ?? ($assignment?->status === 'completed' ? $assignment?->updated_at : null);
    }

    private function paperAction(Submission $submission, string $key, string $titleId, string $titleEn, string $descriptionId, string $descriptionEn, string $labelId, string $labelEn, int $progress, string $actor = 'author'): array
    {
        return $this->action($key, $titleId, $titleEn, $descriptionId, $descriptionEn, PaperResource::getUrl('view', ['record' => $submission], panel: 'author'), $labelId, $labelEn, $progress, $actor);
    }

    private function paperReference(Submission $submission): string
    {
        return 'Abstrak #'.str_pad((string) $submission->id, 5, '0', STR_PAD_LEFT);
    }

    private function action(string $key, string $titleId, string $titleEn, string $descriptionId, string $descriptionEn, string $route, string $labelId, string $labelEn, int $progress, string $actor = 'author'): array
    {
        $id = app()->getLocale() === 'id';

        return [
            'key' => $key,
            'title' => $id ? $titleId : $titleEn,
            'description' => $id ? $descriptionId : $descriptionEn,
            'route' => $route,
            'label' => $id ? $labelId : $labelEn,
            'progress' => $progress,
            'actor' => $this->actorMeta($actor),
            'deadline' => $this->deadlineFor($key),
        ];
    }

    private function actorMeta(string $actor): array
    {
        $id = app()->getLocale() === 'id';
        $labels = [
            'author' => ['Menunggu tindakan Anda', 'Waiting for your action', 'primary', 'heroicon-o-user'],
            'reviewer' => ['Sedang diproses reviewer', 'Being processed by reviewer', 'info', 'heroicon-o-magnifying-glass'],
            'committee' => ['Sedang diproses panitia', 'Being processed by committee', 'info', 'heroicon-o-user-group'],
            'complete' => ['Tidak ada tindakan diperlukan', 'No action required', 'success', 'heroicon-o-check-circle'],
        ];
        [$labelId, $labelEn, $color, $icon] = $labels[$actor] ?? $labels['author'];

        return [
            'key' => $actor,
            'label' => $id ? $labelId : $labelEn,
            'color' => $color,
            'icon' => $icon,
        ];
    }

    private function deadlineFor(string $key): ?array
    {
        $edition = currentEdition();
        if (! $edition) {
            return null;
        }

        $patterns = match ($key) {
            'profile', 'submission' => ['abstract', 'abstrak', 'submission'],
            'review', 'closed' => ['acceptance', 'penerimaan', 'pengumuman'],
            'registration', 'payment', 'waiting' => ['payment', 'pembayaran', 'registration', 'registrasi'],
            'extended', 'verification', 'revision' => ['full paper', 'camera-ready', 'camera ready', 'extended'],
            'complete' => ['conference', 'konferensi', 'pelaksanaan'],
            default => [],
        };

        $dates = $edition->importantDates()->orderBy('date')->get();
        $matches = $dates->filter(function ($item) use ($patterns): bool {
            $labels = Str::lower(implode(' ', $item->getTranslations('label')));

            return collect($patterns)->contains(fn (string $pattern) => Str::contains($labels, $pattern));
        });

        $deadline = $matches->first(fn ($item) => $item->date->startOfDay()->gte(today())) ?? $matches->last();
        if (! $deadline && $key === 'complete' && $edition->start_date) {
            $date = $edition->start_date;
            $label = app()->getLocale() === 'id' ? 'Hari pelaksanaan konferensi' : 'Conference day';
        } elseif ($deadline) {
            $date = $deadline->date;
            $label = $deadline->label;
        } else {
            return null;
        }

        $days = (int) today()->diffInDays($date->copy()->startOfDay(), false);
        $relative = match (true) {
            $days < 0 => app()->getLocale() === 'id' ? 'Deadline telah lewat' : 'Deadline has passed',
            $days === 0 => app()->getLocale() === 'id' ? 'Hari ini' : 'Today',
            $days === 1 => app()->getLocale() === 'id' ? 'Besok' : 'Tomorrow',
            default => app()->getLocale() === 'id' ? "{$days} hari lagi" : "{$days} days remaining",
        };

        return compact('label', 'date', 'days', 'relative');
    }
}
