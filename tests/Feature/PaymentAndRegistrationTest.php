<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Edition;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Services\MidtransService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PaymentAndRegistrationTest extends TestCase
{
    public function test_webhook_rejects_invalid_signature(): void
    {
        config()->set('services.midtrans.server_key', 'server-key');

        $this->postJson(route('payment.midtrans.notification'), [
            'order_id' => 'fake-order',
            'status_code' => '200',
            'gross_amount' => '750000.00',
            'signature_key' => 'invalid',
        ])->assertForbidden();
    }

    public function test_registration_uses_price_for_the_active_period(): void
    {
        Carbon::setTestNow('2026-08-21 12:00:00');

        $fee = new RegistrationFee([
            'price_early_bird' => 500_000,
            'price_regular' => 750_000,
            'early_bird_deadline' => '2026-08-31',
        ]);

        $this->assertSame('500000.00', $fee->currentPrice());

        $fee->early_bird_deadline = '2026-08-20';
        $this->assertSame('750000.00', $fee->currentPrice());

        Carbon::setTestNow();
    }

    public function test_auto_invoice_ignores_fees_from_an_inactive_edition(): void
    {
        $active = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $inactive = Edition::create(['name' => 'ICOMAN 2025', 'is_active' => false]);
        $author = Author::create([
            'name' => 'Author',
            'email' => 'author@example.test',
            'password' => 'secret-password',
            'participation_type' => 'participant',
        ]);
        RegistrationFee::create([
            'edition_id' => $inactive->id,
            'category' => ['en' => 'Presenter'],
            'audience' => 'participant',
            'price_regular' => 750_000,
            'currency' => 'IDR',
        ]);

        // Tarif hanya ada di edisi non-aktif → tidak ada invoice yang dibuat.
        $this->assertNull(app(\App\Services\RegistrationProvisioner::class)->ensureFor($author));
        $this->assertDatabaseCount('registrations', 0);
        $this->assertTrue($active->is_active);
    }

    public function test_valid_webhook_marks_payment_paid_and_late_webhook_cannot_downgrade_it(): void
    {
        config()->set('services.midtrans.server_key', 'server-key');

        [$registration, $payment] = $this->gatewayPayment();
        $service = app(MidtransService::class);

        $service->applyNotification($this->payload($payment, 'settlement'));
        $this->assertSame('paid', $registration->refresh()->status);
        $this->assertNotNull($registration->paid_at);

        $service->applyNotification($this->payload($payment, 'expire'));
        $this->assertSame('paid', $registration->refresh()->status);
        $this->assertNotNull($registration->paid_at);
    }

    public function test_webhook_rejects_amount_mismatch(): void
    {
        config()->set('services.midtrans.server_key', 'server-key');
        [$registration, $payment] = $this->gatewayPayment();

        $payload = $this->payload($payment, 'settlement');
        $payload['gross_amount'] = '1.00';

        $result = app(MidtransService::class)->applyNotification($payload);

        $this->assertNull($result);
        $this->assertSame('pending', $registration->refresh()->status);
    }

    private function gatewayPayment(): array
    {
        $edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $author = Author::create([
            'name' => 'Author',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
        ]);
        $fee = RegistrationFee::create([
            'edition_id' => $edition->id,
            'category' => ['en' => 'Presenter'],
            'price_regular' => 750_000,
            'currency' => 'IDR',
        ]);
        $registration = Registration::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'registration_fee_id' => $fee->id,
            'payment_method' => 'gateway',
            'amount' => 750_000,
            'status' => 'pending',
            'gateway_transaction_id' => 'ICOMAN-'.$edition->id.'-TEST',
        ]);
        $payment = Payment::create([
            'registration_id' => $registration->id,
            'method' => 'gateway',
            'gateway_name' => 'midtrans',
            'gateway_reference' => $registration->gateway_transaction_id,
            'amount' => 750_000,
            'status' => 'initiated',
        ]);

        return [$registration, $payment];
    }

    private function payload(Payment $payment, string $status): array
    {
        return [
            'order_id' => $payment->gateway_reference,
            'gross_amount' => '750000.00',
            'transaction_status' => $status,
            'fraud_status' => 'accept',
        ];
    }
}
