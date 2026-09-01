<?php

namespace App\Http\Controllers\Author;

use App\Filament\Author\Pages\AuthorProfile;
use App\Filament\Author\Resources\Registrations\RegistrationResource;
use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Services\MidtransService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->to(RegistrationResource::getUrl(panel: 'author'));
    }

    public function create(Request $request): RedirectResponse
    {
        $author = Auth::guard('author')->user();

        if (! $author->participation_type) {
            return redirect()->to(AuthorProfile::getUrl(panel: 'author'))->with('error', app()->getLocale() === 'id'
                ? 'Pilih jalur partisipasi sebelum membuat registrasi.'
                : 'Choose your participation path before creating a registration.');
        }

        return redirect()->to(RegistrationResource::getUrl('create', array_filter([
            'submission' => $request->integer('submission') ?: null,
        ]), panel: 'author'));
    }

    public function store(Request $request): RedirectResponse
    {
        $edition = currentEdition();
        abort_unless($edition, 503, 'Tidak ada edition aktif.');

        $author = Auth::guard('author')->user();
        abort_unless($author->participation_type, 403);
        $audience = $author->isPresenter() ? 'presenter' : 'participant';

        $data = $request->validate([
            'registration_fee_id' => [
                'required',
                Rule::exists('registration_fees', 'id')->where(fn ($query) => $query
                    ->where('edition_id', $edition->id)
                    ->where('audience', $audience)),
            ],
            'payment_method' => ['required', 'in:manual,gateway'],
            'submission_id' => [$author->isPresenter() ? 'required' : 'prohibited', 'nullable', 'integer', 'exists:submissions,id'],
        ]);

        $submission = null;
        if ($author->isPresenter()) {
            $submission = $author->submissions()
                ->where('edition_id', $edition->id)
                ->where('status', 'accepted')
                ->find($data['submission_id']);

            if (! $submission) {
                throw ValidationException::withMessages([
                    'submission_id' => app()->getLocale() === 'id'
                        ? 'Pilih paper Anda yang sudah dinyatakan accepted.'
                        : 'Select one of your accepted papers.',
                ]);
            }
        }

        $existing = $author->registrations()
            ->where('edition_id', $edition->id)
            ->whereIn('status', ['pending', 'pending_verification', 'paid'])
            ->when($submission, fn ($query) => $query->where('submission_id', $submission->id), fn ($query) => $query->whereNull('submission_id'))
            ->latest()
            ->first();

        if ($existing) {
            return redirect()->route('author.registration.show', $existing)->with('error', app()->getLocale() === 'id'
                ? 'Registrasi aktif untuk jalur ini sudah tersedia. Lanjutkan dari halaman ini.'
                : 'An active registration already exists for this path. Continue from this page.');
        }

        $fee = RegistrationFee::whereBelongsTo($edition)->where('audience', $audience)->findOrFail($data['registration_fee_id']);
        $amount = $fee->currentPrice();

        $registration = Registration::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'registration_fee_id' => $fee->id,
            'submission_id' => $submission?->id,
            'payment_method' => $data['payment_method'],
            'amount' => $amount,
            'status' => 'pending',
        ]);

        if ($data['payment_method'] === 'gateway') {
            return $this->startGateway($registration);
        }

        return redirect()->route('author.registration.show', $registration);
    }

    public function show(Registration $registration): RedirectResponse
    {
        $this->authorizeOwner($registration);

        return redirect()->to(RegistrationResource::getUrl('view', ['record' => $registration], panel: 'author'));
    }

    public function uploadProof(Request $request, Registration $registration): RedirectResponse
    {
        $this->authorizeOwner($registration);

        abort_unless($registration->payment_method === 'manual', 403);
        abort_if($registration->status === 'paid', 403);

        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $registration->clearMediaCollection('payment_proof');
        $registration->addMediaFromRequest('proof')->toMediaCollection('payment_proof');

        $registration->update(['status' => 'pending_verification']);

        $payment = $registration->payments()->where('method', 'manual')->where('status', 'initiated')->latest()->first();
        $payment
            ? $payment->update(['amount' => $registration->amount])
            : $registration->payments()->create(['method' => 'manual', 'amount' => $registration->amount, 'status' => 'initiated']);

        return back()->with('status', __('Bukti transfer terunggah. Menunggu verifikasi admin.'));
    }

    public function payGateway(Registration $registration): RedirectResponse
    {
        $this->authorizeOwner($registration);

        abort_if($registration->status === 'paid', 403);
        abort_unless($registration->payment_method === 'gateway', 403);

        return $this->startGateway($registration);
    }

    public function changePaymentMethod(Request $request, Registration $registration): RedirectResponse
    {
        $this->authorizeOwner($registration);
        abort_unless(in_array($registration->status, ['pending', 'failed'], true), 403);

        $data = $request->validate(['payment_method' => ['required', 'in:manual,gateway']]);

        $registration->update([
            'payment_method' => $data['payment_method'],
            'status' => 'pending',
            'gateway_transaction_id' => null,
            'gateway_payload' => null,
        ]);

        return back()->with('status', app()->getLocale() === 'id'
            ? 'Metode pembayaran berhasil diubah.'
            : 'Payment method updated successfully.');
    }

    private function startGateway(Registration $registration): RedirectResponse
    {
        $midtrans = app(MidtransService::class);

        if (! $midtrans->isConfigured()) {
            return redirect()
                ->route('author.registration.show', $registration)
                ->with('error', __('Payment gateway belum dikonfigurasi. Silakan pilih transfer manual atau hubungi panitia.'));
        }

        try {
            $url = $midtrans->createSnapRedirect($registration);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('author.registration.show', $registration)
                ->with('error', __('Gagal memulai pembayaran gateway. Coba lagi atau gunakan transfer manual.'));
        }

        return redirect()->away($url);
    }

    private function authorizeOwner(Registration $registration): void
    {
        abort_unless($registration->author_id === Auth::guard('author')->id(), 403);
    }
}
