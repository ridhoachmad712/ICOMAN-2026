<?php

namespace Tests\Feature;

use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Registrations\Pages\ListRegistrations;
use App\Filament\Resources\Registrations\RegistrationResource;
use App\Filament\Widgets\LatestSubmissions;
use App\Filament\Widgets\RegistrationStats;
use App\Filament\Widgets\SubmissionFunnel;
use App\Filament\Widgets\SubmissionWorkboard;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Akun bendahara.
 *
 * Panitia memutuskan bendahara mengurus uang saja. Yang menarik dari peran ini
 * bukan apa yang boleh ia lihat, melainkan apa yang tidak: naskah, tarif,
 * voucher, pengaturan, dan data peserta di luar keperluan pembayaran.
 *
 * Ia boleh mencatat pembayaran yang tidak terkabar gateway — dan sejak ada
 * peran kedua yang boleh menekan tombol itu, namanya harus ikut tercatat.
 */
class TreasurerRoleTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
    }

    public function test_the_treasurer_can_open_the_panel_and_reach_money_pages(): void
    {
        $treasurer = $this->treasurer();
        $this->actingAs($treasurer, 'web');

        $this->assertTrue($treasurer->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue(PaymentResource::canAccess());
        $this->assertTrue(RegistrationResource::canAccess());
    }

    /** Yang membedakan peran ini justru daftar yang tidak boleh disentuhnya. */
    public function test_the_treasurer_is_kept_away_from_everything_that_is_not_money(): void
    {
        $this->actingAs($this->treasurer(), 'web');

        $closed = [
            \App\Filament\Resources\Submissions\SubmissionResource::class,
            \App\Filament\Resources\RegistrationFees\RegistrationFeeResource::class,
            \App\Filament\Resources\Vouchers\VoucherResource::class,
            \App\Filament\Resources\CoHosts\CoHostResource::class,
            \App\Filament\Resources\Authors\AuthorResource::class,
            \App\Filament\Resources\Users\UserResource::class,
            \App\Filament\Resources\News\NewsResource::class,
        ];

        foreach ($closed as $resource) {
            $this->assertFalse($resource::canAccess(), class_basename($resource).' seharusnya tertutup bagi bendahara.');
        }

        $this->assertFalse(\App\Filament\Pages\Settings\ManageSiteSettings::canAccess(), 'Pengaturan memuat API key gateway.');
    }

    /** Transaksi tetap catatan: bendahara pun tidak boleh menyuntingnya. */
    public function test_the_treasurer_still_cannot_edit_a_transaction(): void
    {
        $this->actingAs($this->treasurer(), 'web');

        $this->assertFalse(PaymentResource::canCreate());
        $this->assertFalse(PaymentResource::canEdit(null));
        $this->assertFalse(PaymentResource::canDelete(null));
    }

    public function test_the_dashboard_shows_money_and_nothing_about_papers(): void
    {
        $this->actingAs($this->treasurer(), 'web');

        $this->assertTrue(RegistrationStats::canView());
        $this->assertFalse(SubmissionWorkboard::canView());
        $this->assertFalse(SubmissionFunnel::canView());
        $this->assertFalse(LatestSubmissions::canView());
    }

    /**
     * Pencatatan manual meninggalkan nama. Tanpa ini, peran kedua yang boleh
     * menandai lunas membuat catatan pembayaran jadi tidak bisa ditelusuri
     * ke siapa pun.
     */
    public function test_marking_an_invoice_paid_records_who_did_it(): void
    {
        $treasurer = $this->treasurer();
        $registration = $this->registration();

        $this->actingAs($treasurer, 'web');

        Livewire::test(ListRegistrations::class)
            ->callAction(TestAction::make('verify')->table($registration));

        $payment = $registration->payments()->where('method', 'manual')->firstOrFail();

        $this->assertSame($treasurer->id, $payment->recorded_by);
        $this->assertSame($treasurer->name, $payment->recordedBy->name);
    }

    /** Pembayaran lewat gateway tidak punya pelaku, dan itu memang benar. */
    public function test_a_gateway_payment_has_nobody_behind_it(): void
    {
        $registration = $this->registration();

        $payment = $registration->payments()->create([
            'method' => 'gateway',
            'gateway_name' => 'borderpay',
            'gateway_reference' => 'ICOMAN-'.$registration->id.'-X',
            'amount' => $registration->amount,
            'status' => 'success',
        ]);

        $this->assertNull($payment->recorded_by);
    }

    private function treasurer(): User
    {
        Role::findOrCreate('bendahara', 'web');

        $user = User::create([
            'name' => 'Bendahara Panitia',
            'email' => 'bendahara-'.uniqid().'@example.test',
            'password' => 'secret-password',
        ]);
        $user->assignRole('bendahara');

        return $user;
    }

    private function registration(): Registration
    {
        $author = Author::create([
            'name' => 'Peserta',
            'email' => 'peserta-'.uniqid().'@example.test',
            'password' => 'secret-password',
            'participation_type' => 'participant',
            'registrant_category' => 'general',
        ]);

        $fee = RegistrationFee::create([
            'edition_id' => $this->edition->id,
            'category' => ['en' => 'Participant'],
            'audience' => 'participant',
            'registrant_category' => 'general',
            'price_regular' => 500_000,
            'currency' => 'IDR',
        ]);

        return Registration::create([
            'edition_id' => $this->edition->id,
            'author_id' => $author->id,
            'registration_fee_id' => $fee->id,
            'amount' => 500_000,
            'currency' => 'IDR',
            'payment_method' => 'gateway',
            'status' => 'pending',
        ]);
    }
}
