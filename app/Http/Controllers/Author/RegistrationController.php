<?php

namespace App\Http\Controllers\Author;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Services\MidtransService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function create(): View
    {
        return view('author.registration.create', [
            'fees' => RegistrationFee::where('edition_id', currentEdition()?->id)->orderBy('order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'registration_fee_id' => ['required', 'exists:registration_fees,id'],
            'payment_method' => ['required', 'in:manual,gateway'],
        ]);

        $fee = RegistrationFee::findOrFail($data['registration_fee_id']);
        $amount = $fee->price_early_bird ?? $fee->price_regular;

        $registration = Registration::create([
            'edition_id' => currentEdition()?->id,
            'author_id' => Auth::guard('author')->id(),
            'registration_fee_id' => $fee->id,
            'payment_method' => $data['payment_method'],
            'amount' => $amount,
            'status' => 'pending',
        ]);

        if ($data['payment_method'] === 'gateway') {
            return $this->startGateway($registration);
        }

        return redirect()->route('author.registration.show', $registration);
    }

    public function show(Registration $registration): View
    {
        $this->authorizeOwner($registration);

        $registration->load(['registrationFee', 'payments' => fn ($q) => $q->latest()]);

        return view('author.registration.show', compact('registration'));
    }

    public function uploadProof(Request $request, Registration $registration): RedirectResponse
    {
        $this->authorizeOwner($registration);

        abort_unless($registration->payment_method === 'manual', 403);

        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $registration->clearMediaCollection('payment_proof');
        $registration->addMediaFromRequest('proof')->toMediaCollection('payment_proof');

        $registration->update(['status' => 'pending_verification']);

        $registration->payments()->create([
            'method' => 'manual',
            'amount' => $registration->amount,
            'status' => 'initiated',
        ]);

        return back()->with('status', __('Bukti transfer terunggah. Menunggu verifikasi admin.'));
    }

    public function payGateway(Registration $registration): RedirectResponse
    {
        $this->authorizeOwner($registration);

        abort_if($registration->status === 'paid', 403);

        return $this->startGateway($registration);
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
