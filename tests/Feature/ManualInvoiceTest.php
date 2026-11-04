<?php

namespace Tests\Feature;

use App\Filament\Author\Resources\Registrations\Pages\ViewRegistration;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Resources\Registrations\Pages\ListRegistrations;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\Submission;
use App\Models\User;
use App\Services\RegistrationProvisioner;
use App\Settings\SiteSettings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Peserta yang membayar di luar portal saat pembayaran ditutup: panitia
 * membuat invoice-nya, lalu menandainya lunas.
 */
class ManualInvoiceTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        foreach (['presenter', 'participant'] as $audience) {
            RegistrationFee::create([
                'edition_id' => $this->edition->id, 'category' => ['en' => ucfirst($audience)],
                'audience' => $audience, 'registrant_category' => 'general',
                'price_regular' => 400_000, 'currency' => 'IDR',
            ]);
        }
        app(SiteSettings::class)->fill(['payment_closed' => true, 'sinta3_fee' => 300_000])->save();
    }

    public function test_only_the_committee_path_can_create_an_invoice_while_payments_are_closed(): void
    {
        $author = $this->paper()->author;
        $provisioner = app(RegistrationProvisioner::class);

        try {
            $provisioner->ensureFor($author);
            $this->fail('Portal tidak boleh membuat invoice saat pembayaran ditutup.');
        } catch (ValidationException) {
            $this->assertSame(0, Registration::count());
        }

        $invoice = $provisioner->ensureFor($author, byCommittee: true);

        $this->assertSame('pending', $invoice->status);
        $this->assertSame(400000.0, (float) $invoice->amount);
        $this->assertSame($invoice->id, $provisioner->ensureFor($author, byCommittee: true)->id, 'Tidak boleh ganda.');
    }

    public function test_admin_creates_the_invoice_then_marks_it_paid(): void
    {
        $paper = $this->paper();
        $this->admin();

        Livewire::test(ListAuthors::class)
            ->callAction(TestAction::make('createInvoice')->table($paper->author))
            ->assertHasNoFormErrors();

        $invoice = Registration::sole();
        $this->assertSame($paper->id, $invoice->submission_id);

        Livewire::test(ListRegistrations::class)
            ->callAction(TestAction::make('verify')->table($invoice));

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(400000.0, $invoice->paidAmount());
    }

    public function test_the_button_only_appears_for_accounts_that_have_reached_payment(): void
    {
        $ready = $this->paper()->author;
        $noLoa = $this->paper(['loa_issued_at' => null])->author;
        $underReview = $this->paper(['status' => 'extended_abstract_submitted', 'loa_issued_at' => null])->author;
        $attendee = $this->account('participant');
        $invoiced = $this->paper()->author;
        app(RegistrationProvisioner::class)->ensureFor($invoiced, byCommittee: true);
        $this->admin();

        Livewire::test(ListAuthors::class)
            ->assertActionVisible(TestAction::make('createInvoice')->table($ready))
            ->assertActionVisible(TestAction::make('createInvoice')->table($attendee))
            ->assertActionHidden(TestAction::make('createInvoice')->table($noLoa))
            ->assertActionHidden(TestAction::make('createInvoice')->table($underReview))
            ->assertActionHidden(TestAction::make('createInvoice')->table($invoiced));
    }

    public function test_a_missing_fee_is_reported_without_creating_an_invoice(): void
    {
        RegistrationFee::query()->delete();
        $author = $this->paper()->author;
        $this->admin();

        Livewire::test(ListAuthors::class)
            ->callAction(TestAction::make('createInvoice')->table($author))
            ->assertNotified('Invoice tidak dapat dibuat');

        $this->assertSame(0, Registration::count());
    }

    public function test_marking_paid_is_locked_until_the_journal_is_chosen(): void
    {
        $paper = $this->paper(['sinta3_offered' => true]);
        $invoice = app(RegistrationProvisioner::class)->ensureFor($paper->author, byCommittee: true);
        $this->admin();

        $page = Livewire::test(ListRegistrations::class)
            ->assertActionDisabled(TestAction::make('verify')->table($invoice));
        $page->callAction(TestAction::make('verify')->table($invoice));
        $this->assertSame('pending', $invoice->refresh()->status);

        $paper->update(['journal_target' => 'regular', 'journal_target_chosen_at' => now()]);

        Livewire::test(ListRegistrations::class)
            ->assertActionEnabled(TestAction::make('verify')->table($invoice));
    }

    public function test_presenter_can_still_choose_a_journal_while_payments_are_closed(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('author'));
        $paper = $this->paper(['sinta3_offered' => true]);
        $invoice = app(RegistrationProvisioner::class)->ensureFor($paper->author, byCommittee: true);
        $this->actingAs($paper->author, 'author');

        Livewire::test(ViewRegistration::class, ['record' => $invoice->getRouteKey()])
            ->assertSee(route('author.registration.journal', $invoice), escape: false);

        $this->patch(route('author.registration.journal', $invoice), ['journal_target' => 'sinta3'])
            ->assertRedirect();

        $this->assertSame(700000.0, (float) $invoice->refresh()->amount);
        $this->assertTrue($paper->refresh()->journalTargetIsChosen());
    }

    private function admin(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('admin_registrasi', 'web');
        $admin = User::create(['name' => 'Admin', 'email' => 'invoice-admin@example.test', 'password' => 'secret-password']);
        $admin->assignRole('admin_registrasi');
        $this->actingAs($admin, 'web');
    }

    private function account(string $type): Author
    {
        return Author::create([
            'name' => 'Peserta '.uniqid(), 'email' => fake()->unique()->safeEmail(), 'password' => 'secret-password',
            'participation_type' => $type, 'registrant_category' => 'general',
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function paper(array $attributes = []): Submission
    {
        return Submission::create(array_merge([
            'edition_id' => $this->edition->id, 'author_id' => $this->account('presenter')->id, 'title' => 'Paper',
            'abstract' => 'Abstract', 'keywords' => ['k'], 'status' => 'accepted', 'loa_issued_at' => now(),
        ], $attributes));
    }
}
