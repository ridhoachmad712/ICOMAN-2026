<?php

namespace App\Http\Controllers\Author;

use App\Filament\Author\Pages\AuthorDashboard;
use App\Filament\Author\Pages\AuthorProfile;
use App\Filament\Author\Resources\Registrations\RegistrationResource;
use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Services\BorderpayService;
use App\Services\RegistrationProvisioner;
use App\Services\VoucherRedeemer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

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
        abort_unless($registration->isPayable(), 403);

        $submission = $registration->submission;
        abort_unless($submission && $submission->sinta3_offered, 403);

        $data = $request->validate(['journal_target' => ['required', 'in:regular,sinta3']]);

        DB::transaction(function () use ($registration, $data): void {
            $locked = Registration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['pending', 'failed'], true), 403);
            if ($locked->hasUnresolvedPayment()) {
                throw ValidationException::withMessages(['payment' => app()->getLocale() === 'id'
                    ? 'Pilihan jurnal terkunci selama transaksi aktif atau pembayaran perlu direkonsiliasi. Periksa status pembayaran terlebih dahulu.'
                    : 'The journal option is locked while a payment is active or requires reconciliation. Check payment status first.']);
            }
            $price = $locked->priceDetails();
            abort_if($price['legacy'] ?? false, 409, 'Contact the committee to amend an archived invoice.');
            $price['addon_amount'] = $data['journal_target'] === 'sinta3' ? $price['quoted_addon_amount'] : 0;
            $price['journal_target'] = $data['journal_target'];
            $locked->submission->update([
                'journal_target' => $data['journal_target'],
                'journal_target_chosen_at' => now(),
            ]);
            $locked->update([
                'amount' => max(0, $price['base_amount'] + $price['addon_amount'] - (int) ($price['discount_amount'] ?? 0)),
                'pricing_snapshot' => $price,
            ]);
        });

        // Sengaja BUKAN back(): pemilihnya dibuka lewat ?step=journal, dan back()
        // akan mengembalikan author ke langkah pemilihan yang baru saja selesai.
        return redirect()
            ->to(RegistrationResource::getUrl('view', ['record' => $registration], panel: 'author'))
            ->with('status', app()->getLocale() === 'id'
                ? 'Pilihan jurnal tersimpan. Tagihan Anda sudah menyesuaikan.'
                : 'Journal choice saved. Your invoice has been updated.');
    }

    /** Menukarkan kode voucher pada invoice yang belum dibayar. */
    public function redeemVoucher(Request $request, Registration $registration): RedirectResponse
    {
        $this->authorizeOwner($registration);

        $data = $request->validate([
            'voucher_code' => ['required', 'string', 'max:40'],
        ]);

        $voucher = app(VoucherRedeemer::class)->redeem($registration, $data['voucher_code']);

        return back()->with('status', app()->getLocale() === 'id'
            ? 'Voucher '.$voucher->code.' berhasil dipakai. Total tagihan telah diperbarui.'
            : 'Voucher '.$voucher->code.' was applied. Your invoice total has been updated.');
    }

    public function payGateway(Request $request, Registration $registration): RedirectResponse
    {
        $this->authorizeOwner($registration);

        abort_if($registration->status === 'paid', 403);

        // Pilihan mencicil diverifikasi ulang di service; di sini hanya niatnya.
        return $this->startGateway($registration, $request->input('plan') === 'installment');
    }

    private function startGateway(Registration $registration, bool $installment = false): RedirectResponse
    {
        $borderpay = app(BorderpayService::class);

        if (! $borderpay->isConfigured()) {
            return redirect()
                ->route('author.registration.show', $registration)
                ->with('error', __('Pembayaran online belum dapat digunakan saat ini. Silakan coba beberapa saat lagi atau hubungi panitia.'));
        }

        try {
            $url = $borderpay->createCheckoutRedirect($registration, $installment);
        } catch (ValidationException $e) {
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
            app(BorderpayService::class)->synchronize($registration);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', app()->getLocale() === 'id'
                ? 'Konfirmasi gateway belum tersedia. Jangan membayar ulang; periksa kembali atau hubungi panitia dengan nomor invoice.'
                : 'Gateway confirmation is not available yet. Do not pay again; check later or contact the committee with your invoice number.');
        }

        return back()->with('status', app()->getLocale() === 'id' ? 'Status pembayaran diperbarui.' : 'Payment status refreshed.');
    }

    public function cancelUnpaidCheckout(Registration $registration): RedirectResponse
    {
        $this->authorizeOwner($registration);

        try {
            app(BorderpayService::class)->cancelUnpaidCheckout($registration);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', app()->getLocale() === 'id'
                ? 'BorderPay belum dapat mengonfirmasi pembatalan. Pilihan pembayaran tetap terkunci; periksa status atau coba lagi nanti.'
                : 'BorderPay could not confirm cancellation. Payment choices remain locked; check status or try again later.');
        }

        return back()->with('status', app()->getLocale() === 'id'
            ? 'Transaksi lama dibatalkan. Anda dapat memilih cara pembayaran lain atau memakai voucher.'
            : 'The previous checkout was cancelled. You can now choose another payment plan or apply a voucher.');
    }

    /**
     * Bukti transfer untuk pengurus uang (superadmin, admin registrasi,
     * bendahara). Berkasnya di disk privat, jadi ini satu-satunya pintunya.
     */
    public function paymentProofForAdmin(Registration $registration): Response
    {
        abort_unless(Auth::guard('web')->user()?->handlesMoney(), 403);

        $media = $registration->getFirstMedia('payment_proof');
        abort_unless($media && is_file($media->getPath()), 404);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $media->file_name, 'payment-proof'),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
        ]);
    }
}
