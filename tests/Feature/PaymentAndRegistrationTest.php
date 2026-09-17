<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\ManageSiteSettings;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\User;
use App\Services\BorderpayService;
use App\Services\RegistrationProvisioner;
use App\Settings\SiteSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentAndRegistrationTest extends TestCase
{
    /**
     * Webhook datang dari server BorderPay tanpa CSRF token, jadi route-nya
     * harus ada di daftar pengecualian. CSRF tidak aktif saat tes berjalan,
     * sehingga tes HTTP mana pun tetap hijau walau pengecualiannya salah
     * alamat; itu persis yang sempat terjadi saat route-nya dipindah dari
     * Midtrans. Daftarnya karena itu dibaca langsung.
     */
    public function test_the_webhook_route_is_exempt_from_csrf(): void
    {
        $excluded = app(ValidateCsrfToken::class)->getExcludedPaths();

        $this->assertContains(
            ltrim(parse_url(route('payment.borderpay.notification'), PHP_URL_PATH), '/'),
            $excluded,
        );
    }

    /**
     * Kredensial diisi lewat form admin, bukan ditulis ke model. Tes yang
     * menulis langsung ke settings pernah menyembunyikan field yang salah nama
     * dan diam-diam dibuang saat disimpan.
     */
    public function test_the_gateway_credentials_are_saved_from_the_admin_form(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('superadmin', 'web');
        $admin = User::create(['name' => 'Super', 'email' => 'super-borderpay@example.test', 'password' => 'secret-password']);
        $admin->assignRole('superadmin');
        $this->actingAs($admin, 'web');

        Livewire::test(ManageSiteSettings::class)
            ->assertOk()
            ->fillForm(['borderpay_api_key' => 'bp_test_abc', 'borderpay_webhook_token' => 'bpt-abc'])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SiteSettings::class)->refresh();
        $this->assertSame('bp_test_abc', $settings->borderpay_api_key);
        $this->assertSame('bpt-abc', $settings->borderpay_webhook_token);

        // Rahasia tidak boleh tersimpan apa adanya di kolom database.
        $stored = DB::table('settings')
            ->where('group', 'site')->where('name', 'borderpay_api_key')->value('payload');
        $this->assertStringNotContainsString('bp_test_abc', (string) $stored);
    }

    /** Kredensial yang tersimpan dipakai lebih dulu daripada nilai .env. */
    public function test_saved_credentials_win_over_the_env_fallback(): void
    {
        config()->set('services.borderpay.api_key', 'bp_test_from_env');

        $this->assertTrue(app(BorderpayService::class)->isConfigured());

        $settings = app(SiteSettings::class);
        $settings->borderpay_api_key = 'bp_live_from_settings';
        $settings->save();

        $this->assertTrue(app(BorderpayService::class)->isLiveMode());
    }

    public function test_registration_uses_one_fixed_price(): void
    {
        $fee = new RegistrationFee(['price_regular' => 750_000]);
        $this->assertSame('750000.00', $fee->currentPrice());
        $this->assertFalse(Schema::hasColumn('registration_fees', 'price_early_bird'));
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
        $this->assertNull(app(RegistrationProvisioner::class)->ensureFor($author));
        $this->assertDatabaseCount('registrations', 0);
        $this->assertTrue($active->is_active);
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
            'gateway_name' => 'borderpay',
            'gateway_reference' => $registration->gateway_transaction_id,
            'amount' => 750_000,
            'status' => 'initiated',
        ]);

        return [$registration, $payment];
    }
}
