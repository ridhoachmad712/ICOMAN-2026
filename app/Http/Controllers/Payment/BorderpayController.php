<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\BorderpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BorderpayController extends Controller
{
    /**
     * Webhook BorderPay.
     *
     * Tokennya diperiksa untuk menjaga pintu, tapi isinya tidak dipercaya:
     * kiriman BorderPay tidak ditandatangani, jadi payload tidak terikat pada
     * rahasia apa pun. Yang dipakai dari seluruh kiriman hanya `reference_id`,
     * dan statusnya ditanyakan ulang ke gateway.
     */
    public function notification(Request $request, BorderpayService $borderpay): JsonResponse
    {
        if (! $borderpay->verifyToken($request->header('x-borderpay-token'))) {
            Log::warning('BorderPay webhook: invalid token', ['event' => $request->header('x-borderpay-event')]);

            return response()->json(['message' => 'Invalid token'], 401);
        }

        $event = $request->json()->all();

        if (! is_array($event)) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $registration = $borderpay->applyEvent($event);

        if (! $registration) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        // Dokumentasi BorderPay meminta 2xx dibalas SETELAH datanya tersimpan,
        // bukan sebelum. applyEvent() sudah menulis di dalam transaksi.
        return response()->json(['message' => 'OK']);
    }

    /**
     * Author kembali dari halaman pembayaran.
     *
     * Bukan bukti pembayaran: pembeli bisa sampai di URL ini tanpa membayar.
     * Status sebenarnya datang dari webhook atau tombol Periksa Status.
     */
    public function finish(Request $request)
    {
        return redirect()
            ->route('filament.author.pages.author-dashboard')
            ->with('status', __('Pembayaran diproses. Status akan diperbarui otomatis setelah konfirmasi gateway.'));
    }
}
