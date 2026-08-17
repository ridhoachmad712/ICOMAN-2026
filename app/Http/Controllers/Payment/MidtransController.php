<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransController extends Controller
{
    /**
     * Webhook/notification handler Midtrans.
     * WAJIB verifikasi signature sebelum mempercayai payload apa pun.
     */
    public function notification(Request $request, MidtransService $midtrans): JsonResponse
    {
        $payload = $request->all();

        if (! $midtrans->verifySignature($payload)) {
            Log::warning('Midtrans webhook: invalid signature', ['order_id' => $payload['order_id'] ?? null]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $registration = $midtrans->applyNotification($payload);

        if (! $registration) {
            return response()->json(['message' => 'Registration not found'], 404);
        }

        return response()->json(['message' => 'OK']);
    }

    /** Redirect browser setelah user selesai di halaman Snap (non-otoritatif). */
    public function finish(Request $request)
    {
        return redirect()
            ->route('author.dashboard')
            ->with('status', __('Pembayaran diproses. Status akan diperbarui otomatis setelah konfirmasi gateway.'));
    }
}
