<?php

namespace Tests\Feature;

use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Resources\Registrations\Pages\ListRegistrations;
use App\Models\Author;
use App\Models\CoHost;
use App\Models\Edition;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\Submission;
use App\Models\User;
use App\Models\Voucher;
use App\Services\AuthorJourney;
use App\Services\AuthorProgress;
use App\Services\RegistrationProvisioner;
use App\Services\VoucherRedeemer;
use App\Settings\SiteSettings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Presenter yang juga membayar kemitraan co-host: biaya itu tidak menanggung
 * biaya presenter, jadi papernya dilunasi dengan voucher co-host.
 */
class CoHostPresenterInvoiceTest extends TestCase
{
    private Edition $edition;

    private Author $author;

    private Submission $paper;

    private Registration $partnership;

    private Voucher $voucher;

    private CoHost $coHost;

    protected function setUp(): void
    {
        parent::setUp();

        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $this->author = Author::create([
            'name' => 'Dosen Mitra', 'email' => 'mitra@example.test', 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'general',
        ]);
        $this->paper = Submission::create([
            'edition_id' => $this->edition->id, 'author_id' => $this->author->id, 'title' => 'Paper',
            'abstract' => 'Abstract', 'keywords' => ['k'], 'status' => 'accepted', 'loa_issued_at' => now(),
        ]);

        RegistrationFee::create([
            'edition_id' => $this->edition->id, 'category' => ['en' => 'Presenter'],
            'audience' => 'presenter', 'registrant_category' => 'general',
            'price_regular' => 400_000, 'currency' => 'IDR',
        ]);
        $partnershipFee = RegistrationFee::create([
            'edition_id' => $this->edition->id, 'category' => ['en' => 'Co-host'],
            'audience' => 'cohost', 'registrant_category' => 'general',
            'price_regular' => 1_500_000, 'currency' => 'IDR',
        ]);
        $this->partnership = Registration::create([
            'edition_id' => $this->edition->id, 'author_id' => $this->author->id,
            'registration_fee_id' => $partnershipFee->id, 'payment_method' => 'gateway',
            'amount' => 1_500_000, 'currency' => 'IDR', 'status' => 'paid', 'paid_at' => now(),
        ]);
        $this->voucher = Voucher::create([
            'edition_id' => $this->edition->id, 'code' => 'COHOST-MITRA', 'host_name' => 'Universitas Mitra',
            'type' => 'cohost', 'audience' => 'presenter', 'quota' => CoHost::FREE_PAPERS, 'is_active' => true,
        ]);
        $this->coHost = CoHost::create([
            'author_id' => $this->author->id, 'edition_id' => $this->edition->id,
            'institution_name' => 'Universitas Mitra', 'institution_type' => 'university',
            'status' => 'approved', 'voucher_id' => $this->voucher->id,
        ]);
    }

    public function test_a_partnership_fee_does_not_count_as_the_presenter_payment(): void
    {
        $this->author->load('registrations', 'submissions');

        $progress = app(AuthorProgress::class)->snapshot($this->author);
        $this->assertSame('Perlu pembayaran', $progress['label']);
        $this->assertNull($progress['registration']);

        $this->assertFalse($this->author->hasSeminarAccess());
        $this->assertFalse($this->author->canJoinConference());
        $this->assertFalse($this->paper->canSubmitFullPaper());

        $registrations = $this->author->registrations()->with('registrationFee')->get();
        $journey = app(AuthorJourney::class);
        $this->assertSame('payment', $journey->nextAction($this->author, collect([$this->paper]), $registrations)['key']);
        $this->assertSame('current', $journey->timeline($this->author, collect([$this->paper]), $registrations)[2]['state']);
    }

    public function test_committee_settles_the_paper_with_the_cohost_voucher_while_payments_are_closed(): void
    {
        app(SiteSettings::class)->fill(['payment_closed' => true])->save();
        $this->admin();

        Livewire::test(ListAuthors::class)
            ->assertActionVisible(TestAction::make('createInvoice')->table($this->author))
            ->callAction(TestAction::make('createInvoice')->table($this->author));

        $invoice = Registration::where('submission_id', $this->paper->id)->sole();
        $this->assertSame('pending', $invoice->status);

        Livewire::test(ListRegistrations::class)
            ->callAction(TestAction::make('applyVoucher')->table($invoice), ['voucher_code' => 'COHOST-MITRA'])
            ->assertNotified('Voucher diterapkan');

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('waived', $invoice->payment_method);
        $this->assertSame(CoHost::FREE_PAPERS - 1, $this->voucher->fresh()->remainingSlots());
        $this->assertTrue($this->paper->canSubmitFullPaper());

        $this->assertNull($this->partnership->fresh()->submission_id);
        $this->assertTrue($this->coHost->fresh()->isActive(), 'Kemitraannya tetap aktif.');
    }

    public function test_the_portal_stays_closed_while_payments_are_closed(): void
    {
        $invoice = app(RegistrationProvisioner::class)->ensureFor($this->author);
        app(SiteSettings::class)->fill(['payment_closed' => true])->save();

        $this->expectException(ValidationException::class);
        app(VoucherRedeemer::class)->redeem($invoice, 'COHOST-MITRA');
    }

    public function test_a_voucher_refused_by_its_rules_is_reported_without_changing_the_invoice(): void
    {
        $invoice = app(RegistrationProvisioner::class)->ensureFor($this->author);
        $this->voucher->update(['is_active' => false]);
        $this->admin();

        Livewire::test(ListRegistrations::class)
            ->callAction(TestAction::make('applyVoucher')->table($invoice), ['voucher_code' => 'COHOST-MITRA'])
            ->assertNotified('Voucher tidak dapat diterapkan');

        $this->assertSame('pending', $invoice->refresh()->status);
        $this->assertNull($invoice->voucher_id);
    }

    public function test_the_action_is_hidden_on_settled_and_partnership_invoices(): void
    {
        $this->admin();

        Livewire::test(ListRegistrations::class)
            ->assertActionHidden(TestAction::make('applyVoucher')->table($this->partnership));

        $this->partnership->update(['status' => 'pending', 'paid_at' => null]);

        Livewire::test(ListRegistrations::class)
            ->assertActionHidden(TestAction::make('applyVoucher')->table($this->partnership));
    }

    private function admin(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('admin_registrasi', 'web');
        $admin = User::create(['name' => 'Admin', 'email' => 'cohost-invoice-admin@example.test', 'password' => 'secret-password']);
        $admin->assignRole('admin_registrasi');
        $this->actingAs($admin, 'web');
    }
}
