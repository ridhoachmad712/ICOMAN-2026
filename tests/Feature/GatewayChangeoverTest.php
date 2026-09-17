<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Edition;
use App\Models\Payment;
use App\Models\RegistrationFee;
use App\Services\BorderpayGateway;
use App\Services\BorderpayService;
use App\Services\RegistrationProvisioner;
use Tests\TestCase;

/**
 * Apa yang terjadi pada order yang tertinggal saat gateway berganti.
 *
 * Order berstatus `initiated` milik gateway lama tidak ikut berpindah. Selama
 * barisnya masih ada, ia menyimpan tautan checkout gateway lama — dan pemakaian
 * ulang order yang dipasang untuk kasus "author membuka dua tab" akan dengan
 * senang hati menyerahkan tautan itu kembali. Author membayar ke tempat yang
 * statusnya tidak pernah kita tanyakan lagi, lalu invoicenya tetap tertulis
 * belum lunas.
 *
 * Ini sudah terjadi sungguhan setelah perpindahan dari Kasera Pay.
 */
class GatewayChangeoverTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        config()->set('services.borderpay.api_key', 'bp_test_key');
    }

    public function test_an_order_from_the_previous_gateway_is_never_handed_back_to_the_author(): void
    {
        $registration = $this->registration();

        // Persis bentuk baris yang ditinggalkan Kasera: masih berjalan, nominal
        // sama, dan tautan checkoutnya menunjuk ke rumah gateway lama.
        $stale = $registration->payments()->create([
            'method' => 'gateway',
            'gateway_name' => 'kasera',
            'gateway_reference' => 'ICOMAN-'.$registration->id.'-LAMA',
            'amount' => $registration->amount,
            'status' => 'initiated',
            'checkout_url' => 'https://pay.kasera.id/p/lama',
        ]);

        $this->fakeGateway();

        $url = app(BorderpayService::class)->createCheckoutRedirect($registration);

        $this->assertSame('https://borderpay.id/pay/baru', $url, 'Author dikirim kembali ke gateway lama.');
        $this->assertSame('failed', $stale->refresh()->status);
    }

    public function test_the_cleanup_command_cancels_unpaid_orders_from_the_previous_gateway(): void
    {
        $registration = $this->registration();

        $stale = $registration->payments()->create([
            'method' => 'gateway',
            'gateway_name' => 'kasera',
            'gateway_reference' => 'ICOMAN-'.$registration->id.'-LAMA',
            'amount' => $registration->amount,
            'status' => 'initiated',
            'checkout_url' => 'https://pay.kasera.id/p/lama',
        ]);

        $this->artisan('icoman:retire-old-payments')->assertSuccessful();
        $this->assertSame('initiated', $stale->refresh()->status, 'Tanpa --fix tidak boleh ada yang berubah.');

        $this->artisan('icoman:retire-old-payments', ['--fix' => true])->assertSuccessful();
        $this->assertSame('failed', $stale->refresh()->status);
    }

    /**
     * Uang yang sudah benar-benar masuk lewat gateway lama tetap tercatat lunas.
     * Perintah pembersih ini menyentuh order yang belum dibayar saja.
     */
    public function test_the_cleanup_command_leaves_payments_that_actually_succeeded_alone(): void
    {
        $registration = $this->registration();

        $paid = $registration->payments()->create([
            'method' => 'gateway',
            'gateway_name' => 'kasera',
            'gateway_reference' => 'ICOMAN-'.$registration->id.'-LUNAS',
            'amount' => $registration->amount,
            'status' => 'success',
        ]);

        $manual = $registration->payments()->create([
            'method' => 'manual',
            'amount' => $registration->amount,
            'status' => 'initiated',
        ]);

        $this->artisan('icoman:retire-old-payments', ['--fix' => true])->assertSuccessful();

        $this->assertSame('success', $paid->refresh()->status);
        $this->assertSame('initiated', $manual->refresh()->status, 'Pembayaran manual bukan urusan gateway.');
    }

    public function test_an_order_of_the_current_gateway_is_still_reused_across_tabs(): void
    {
        $registration = $this->registration();

        $mine = $registration->payments()->create([
            'method' => 'gateway',
            'gateway_name' => 'borderpay',
            'gateway_reference' => 'ICOMAN-'.$registration->id.'-SEKARANG',
            'amount' => $registration->amount,
            'status' => 'initiated',
            'checkout_url' => 'https://borderpay.id/pay/sekarang',
        ]);

        $this->fakeGateway();

        // Tab kedua harus mendapat halaman bayar yang sama, bukan tagihan baru.
        $url = app(BorderpayService::class)->createCheckoutRedirect($registration);

        $this->assertSame('https://borderpay.id/pay/sekarang', $url);
        $this->assertSame('initiated', $mine->refresh()->status);
        $this->assertSame(1, Payment::where('method', 'gateway')->count());
    }

    private function registration()
    {
        RegistrationFee::create([
            'edition_id' => $this->edition->id,
            'category' => ['en' => 'Participant'],
            'audience' => 'participant',
            'registrant_category' => 'general',
            'price_regular' => 500_000,
            'currency' => 'IDR',
        ]);

        $author = Author::create([
            'name' => 'Portal User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
            'participation_type' => 'participant',
            'registrant_category' => 'general',
        ]);

        return app(RegistrationProvisioner::class)->ensureFor($author);
    }

    private function fakeGateway(): void
    {
        $this->app->singleton(BorderpayGateway::class, fn () => new class extends BorderpayGateway
        {
            public function __construct() {}

            public function createPayment(string $apiKey, array $body): array
            {
                return [
                    'reference_id' => $body['reference_id'],
                    'status' => 'pending',
                    'pay_url' => 'https://borderpay.id/pay/baru',
                ];
            }
        });
    }
}
