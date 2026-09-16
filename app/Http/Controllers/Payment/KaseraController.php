<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\KaseraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KaseraController extends Controller
{
    /**
     * Webhook Kasera Pay.
     *
     * Signature diverifikasi atas RAW body — bukan hasil parse lalu di-encode
     * ulang, karena urutan kunci dan spasi tidak dijamin bertahan dan
     * signature-nya akan meleset.
     */
    public function notification(Request $request, KaseraService $kasera): JsonResponse
    {
        $raw = $request->getContent();

        if (! $kasera->verifySignature($raw, $request->header('Kasera-Signature-V1'))) {
            Log::warning('Kasera webhook: invalid signature', ['event_id' => $request->header('Kasera-Event-Id')]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $event = json_decode($raw, true);

        if (! is_array($event)) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        // Kiriman uji dari dashboard hanya membuktikan konektivitas; tidak ada
        // pembayaran yang perlu dicocokkan, dan harus tetap dijawab 2xx.
        if (($event['type'] ?? null) === 'test.ping') {
            return response()->json(['message' => 'OK']);
        }

        $registration = $kasera->applyEvent($event);

        if (! $registration) {
            return response()->json(['message' => 'Registration not found'], 404);
        }

        return response()->json(['message' => 'OK']);
    }

    /**
     * Author kembali dari halaman checkout.
     *
     * Bukan bukti pembayaran — dokumentasi Kasera menegaskan pembeli bisa
     * sampai di URL ini tanpa membayar. Status sebenarnya datang dari webhook
     * atau tombol Periksa Status.
     */
    public function finish(Request $request)
    {
        return redirect()
            ->route('filament.author.pages.author-dashboard')
            ->with('status', __('Pembayaran diproses. Status akan diperbarui otomatis setelah konfirmasi gateway.'));
    }
}
