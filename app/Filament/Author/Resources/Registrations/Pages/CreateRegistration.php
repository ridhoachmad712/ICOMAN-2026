<?php

namespace App\Filament\Author\Resources\Registrations\Pages;

use App\Filament\Author\Resources\Registrations\RegistrationResource;
use App\Models\Registration;
use App\Models\RegistrationFee;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateRegistration extends CreateRecord
{
    protected static string $resource = RegistrationResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return app()->getLocale() === 'id' ? 'Buat Registrasi' : 'Create Registration';
    }

    public function getSubheading(): ?string
    {
        return app()->getLocale() === 'id'
            ? 'Pilih paket yang sesuai, lalu tentukan metode pembayaran.'
            : 'Choose the applicable package, then select a payment method.';
    }

    protected function handleRecordCreation(array $data): Model
    {
        $author = Filament::auth()->user();
        $edition = currentEdition();

        if (! $edition || ! $author?->participation_type) {
            throw ValidationException::withMessages(['data.registration_fee_id' => app()->getLocale() === 'id' ? 'Lengkapi profil dan pastikan edisi konferensi aktif.' : 'Complete your profile and ensure a conference edition is active.']);
        }

        $audience = $author->isPresenter() ? 'presenter' : 'participant';
        $fee = RegistrationFee::query()->where('edition_id', $edition->id)->where('audience', $audience)->where('registrant_category', $author->feeCategory())->find($data['registration_fee_id'] ?? null);
        if (! $fee) {
            throw ValidationException::withMessages(['data.registration_fee_id' => app()->getLocale() === 'id' ? 'Paket registrasi tidak valid.' : 'The selected registration package is invalid.']);
        }

        $submission = null;
        if ($author->isPresenter()) {
            $submission = $author->submissions()->where('edition_id', $edition->id)->where('status', 'accepted')->whereNotNull('loa_issued_at')->find($data['submission_id'] ?? null);
            if (! $submission) {
                throw ValidationException::withMessages(['data.submission_id' => app()->getLocale() === 'id' ? 'Pilih paper Anda yang LOA-nya sudah diterbitkan.' : 'Select one of your papers whose LOA has been issued.']);
            }
        }

        $existing = $author->registrations()->where('edition_id', $edition->id)
            ->whereIn('status', ['pending', 'pending_verification', 'paid'])
            ->when($submission, fn ($query) => $query->where('submission_id', $submission->id), fn ($query) => $query->whereNull('submission_id'))
            ->exists();
        if ($existing) {
            throw ValidationException::withMessages(['data.registration_fee_id' => app()->getLocale() === 'id' ? 'Registrasi aktif untuk jalur ini sudah tersedia.' : 'An active registration already exists for this path.']);
        }

        // Opsi penerbitan SINTA 3 (hanya bila ditawarkan admin) → biaya tambahan.
        $amount = (float) $fee->currentPrice();
        if ($submission && $submission->sinta3_offered && ($data['journal_target'] ?? 'regular') === 'sinta3') {
            $submission->update(['journal_target' => 'sinta3']);
            $amount += (int) rescue(fn () => siteSettings()->sinta3_fee, 0, false);
        } elseif ($submission) {
            $submission->update(['journal_target' => 'regular']);
        }

        return Registration::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'registration_fee_id' => $fee->id,
            'submission_id' => $submission?->id,
            'payment_method' => $data['payment_method'],
            'amount' => $amount,
            'status' => 'pending',
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return RegistrationResource::getUrl('view', ['record' => $this->record]);
    }
}
