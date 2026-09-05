<?php

namespace App\Http\Controllers\Author;

use App\Filament\Author\Pages\AuthorDashboard;
use App\Filament\Author\Pages\AuthorProfile;
use App\Filament\Author\Resources\Registrations\RegistrationResource;
use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Services\MidtransService;
use App\Services\RegistrationProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegistrationController extends Controller
{
    /**
     * Buat/ambil invoice otomatis dari kategori author, lalu langsung ke halaman
     * pembayaran — menggantikan form registrasi manual.
     */
    public function checkout(): RedirectResponse
    {
        $author = Auth::guard('author')->user();

        if (! $author->participation_type) {
            return redirect()->to(AuthorProfile::getUrl(panel: 'author'))->with('error', app()->getLocale() === 'id'
                ? 'Pilih jalur partisipasi sebelum melanjutkan ke pembayaran.'
                : 'Choose your participation path before continuing to payment.');
        }

        $registration = app(RegistrationProvisioner::class)->ensureFor($author);

        if (! $registration) {
            return redirect()->to(AuthorDashboard::getUrl(panel: 'author'))->with('error', app()->getLocale() === 'id'
                ? 'Tarif untuk kategori Anda belum tersedia, atau paper Anda belum siap untuk pembayaran. Silakan hubungi panitia.'
                : 'A fee for your category is not available yet, or your paper is not ready for payment. Please contact the committee.');
        }

        return redirect()->to(RegistrationResource::getUrl('view', ['record' => $registration], panel: 'author'));
    }

    public function show(Registration $registration): RedirectResponse
    {
        $this->authorizeOwner($registration);

        return redirect()->to(RegistrationResource::getUrl('view', ['record' => $registration], panel: 'author'));
    }

    /** Presenter memilih/mengubah opsi jurnal (SINTA 3) langsung di halaman pembayaran. */
    public function changeJournalTarget(Request $request, Registration $registration): RedirectResponse
    {
        $this->authorizeOwner($registration);
        abort_unless(in_array($registration->status, ['pending', 'failed'], true), 403);

        $submission = $registration->submission;
        abort_unless($submission && $submission->sinta3_offered, 403);

        $data = $request->validate(['journal_target' => ['required', 'in:regular,sinta3']]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($registration, $data): void {
            $locked = Registration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['pending', 'failed'], true), 403);
            if ($locked->hasUnresolvedPayment()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['payment' => app()->getLocale() === 'id'
                    ? 'Pilihan jurnal terkunci selama transaksi aktif atau pembayaran perlu direkonsiliasi. Periksa status pembayaran terlebih dahulu.'
                    : 'The journal option is locked while a payment is active or requires reconciliation. Check payment status first.']);
            }
            $price = $locked->priceDetails();
            abort_if($price['legacy'] ?? false, 409, 'Contact the committee to amend an archived invoice.');
            $price['addon_amount'] = $data['journal_target'] === 'sinta3' ? $price['quoted_addon_amount'] : 0;
            $price['journal_target'] = $data['journal_target'];
            $locked->submission->update(['journal_target' => $data['journal_target']]);
            $locked->update(['amount' => $price['base_amount'] + $price['addon_amount'], 'pricing_snapshot' => $price]);
        });

        return back()->with('status', app()->getLocale() === 'id'
            ? 'Pilihan jurnal diperbarui dan total pembayaran disesuaikan.'
            : 'Journal choice updated and your total has been adjusted.');
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
                ->with('error', __('Pembayaran online belum dapat digunakan saat ini. Silakan coba beberapa saat lagi atau hubungi panitia.'));
        }

        try {
            $url = $midtrans->createSnapRedirect($registration);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('author.registration.show', $registration)
                ->with('error', __('Gagal memulai pembayaran. Silakan coba lagi; bila tetap gagal, hubungi panitia.'));
        }

        return redirect()->away($url);
    }

    private function authorizeOwner(Registration $registration): void
    {
        abort_unless($registration->author_id === Auth::guard('author')->id(), 403);
    }

    public function synchronize(Registration $registration): RedirectResponse
    {
        $this->authorizeOwner($registration);
        try {
            app(MidtransService::class)->synchronize($registration);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', app()->getLocale() === 'id'
                ? 'Konfirmasi gateway belum tersedia. Jangan membayar ulang; periksa kembali atau hubungi panitia dengan nomor invoice.'
                : 'Gateway confirmation is not available yet. Do not pay again; check later or contact the committee with your invoice number.');
        }

        return back()->with('status', app()->getLocale() === 'id' ? 'Status pembayaran diperbarui.' : 'Payment status refreshed.');
    }
}
