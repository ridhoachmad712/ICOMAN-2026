<?php

namespace Tests\Feature;

use App\Filament\Author\Resources\Papers\Pages\EditExtendedAbstract;
use App\Filament\Author\Resources\Papers\PaperResource;
use App\Models\Author;
use App\Models\Edition;
use App\Models\ImportantDate;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\ReviewAssignment;
use App\Models\Submission;
use App\Models\Topic;
use App\Models\User;
use App\Services\AuthorJourney;
use App\Settings\SiteSettings;
use Illuminate\Database\QueryException;
use Livewire\Livewire;
use Tests\TestCase;

class AuthorPortalFlowTest extends TestCase
{
    public function test_registration_choice_is_persisted_on_the_author_account(): void
    {
        // Setelah menyetujui T&C (flag sesi tercatat pada langkah terms).
        $this->withSession(['author_terms_ok' => 'non_presenter'])
            ->post(route('author.register'), [
                'name' => 'Seminar Participant',
                'email' => 'participant@example.test',
                'participation_type' => 'non_presenter',
                'registrant_category' => 'student_s1',
                'password' => 'StrongPassword123!',
                'password_confirmation' => 'StrongPassword123!',
            ])->assertRedirect(\App\Filament\Author\Resources\Registrations\RegistrationResource::getUrl('create', panel: 'author'));

        $this->assertDatabaseHas('authors', [
            'email' => 'participant@example.test',
            'participation_type' => 'participant',
            'registrant_category' => 'student_s1',
        ]);

        $this->assertNotNull(Author::firstOrFail()->terms_accepted_at);
    }

    public function test_registration_requires_accepting_terms_first(): void
    {
        // Tanpa persetujuan T&C, POST register dialihkan ke halaman terms.
        $this->post(route('author.register'), [
            'name' => 'No Terms',
            'email' => 'noterms@example.test',
            'participation_type' => 'presenter',
            'registrant_category' => 'general',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])->assertRedirect(route('author.register.terms', ['role' => 'presenter']));

        $this->assertDatabaseMissing('authors', ['email' => 'noterms@example.test']);
    }

    public function test_registration_form_requires_choosing_a_type_and_terms_first(): void
    {
        // Tanpa role → dialihkan ke halaman pemilihan.
        $this->get(route('author.register.start'))->assertRedirect(route('author.register'));

        // Role valid tapi belum setuju T&C → dialihkan ke halaman terms.
        $this->get(route('author.register.start', ['role' => 'presenter']))
            ->assertRedirect(route('author.register.terms', ['role' => 'presenter']));

        // Menyetujui T&C mencatat flag sesi lalu ke form.
        $this->post(route('author.register.accept-terms'), ['role' => 'presenter'])
            ->assertRedirect(route('author.register.start', ['role' => 'presenter']))
            ->assertSessionHas('author_terms_ok', 'presenter');

        // Dengan flag sesi → form tampil.
        $this->withSession(['author_terms_ok' => 'presenter'])
            ->get(route('author.register.start', ['role' => 'presenter']))->assertOk();
    }

    public function test_registration_fee_is_filtered_by_registrant_category(): void
    {
        $edition = $this->edition();
        $author = $this->author('participant', 'student_s1');
        $generalFee = $this->fee($edition, 'participant', 'general', 750000);
        $studentFee = $this->fee($edition, 'participant', 'student_s1', 450000);

        // Mahasiswa S1 tidak boleh memilih tarif Dosen/Umum.
        $this->actingAs($author, 'author')->post(route('author.registration.store'), [
            'registration_fee_id' => $generalFee->id,
            'payment_method' => 'manual',
        ])->assertSessionHasErrors('registration_fee_id');
        $this->assertDatabaseCount('registrations', 0);

        // Tarif mahasiswa S1 berhasil.
        $this->actingAs($author, 'author')->post(route('author.registration.store'), [
            'registration_fee_id' => $studentFee->id,
            'payment_method' => 'manual',
        ])->assertRedirect();
        $this->assertSame((float) 450000, (float) Registration::firstOrFail()->amount);
    }

    public function test_participant_cannot_select_a_presenter_fee(): void
    {
        $edition = $this->edition();
        $author = $this->author('participant');
        $presenterFee = $this->fee($edition, 'presenter');

        $this->actingAs($author, 'author')->post(route('author.registration.store'), [
            'registration_fee_id' => $presenterFee->id,
            'payment_method' => 'manual',
        ])->assertSessionHasErrors('registration_fee_id');

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_presenter_registration_requires_an_issued_loa(): void
    {
        $edition = $this->edition();
        $author = $this->author('presenter');
        $fee = $this->fee($edition, 'presenter');
        $submission = $this->submission($edition, $author, 'accepted');

        $payload = [
            'registration_fee_id' => $fee->id,
            'submission_id' => $submission->id,
            'payment_method' => 'manual',
        ];

        // Accepted tetapi LOA belum terbit → registrasi ditolak.
        $this->actingAs($author, 'author')->post(route('author.registration.store'), $payload)
            ->assertSessionHasErrors('submission_id');
        $this->assertDatabaseCount('registrations', 0);

        // LOA diterbitkan → registrasi berhasil dan terhubung ke paper.
        $submission->update(['loa_issued_at' => now()]);
        $this->actingAs($author, 'author')->post(route('author.registration.store'), $payload)->assertRedirect();
        $registration = Registration::firstOrFail();
        $this->assertSame($submission->id, $registration->submission_id);

        // Registrasi kedua untuk jalur yang sama tidak boleh terduplikasi.
        $this->actingAs($author, 'author')
            ->post(route('author.registration.store'), $payload)
            ->assertRedirect(route('author.registration.show', $registration));
        $this->assertDatabaseCount('registrations', 1);
    }

    public function test_sinta3_option_adds_a_surcharge_to_the_registration(): void
    {
        $settings = app(SiteSettings::class);
        $settings->sinta3_fee = 250_000;
        $settings->save();

        $edition = $this->edition();
        $author = $this->author('presenter');
        $fee = $this->fee($edition, 'presenter'); // price_regular = 500_000
        $submission = $this->submission($edition, $author, 'accepted');
        $submission->update(['loa_issued_at' => now(), 'sinta3_offered' => true]);

        $this->actingAs($author, 'author')->post(route('author.registration.store'), [
            'registration_fee_id' => $fee->id,
            'submission_id' => $submission->id,
            'payment_method' => 'manual',
            'journal_target' => 'sinta3',
        ])->assertRedirect();

        $registration = Registration::firstOrFail();
        $this->assertEqualsWithDelta(750_000.0, (float) $registration->amount, 0.01);
        $this->assertSame('sinta3', $submission->refresh()->journal_target);
    }

    public function test_presenter_can_submit_only_one_abstract_per_edition(): void
    {
        $edition = $this->edition();
        $author = $this->author('presenter');
        $submission = $this->submission($edition, $author, 'abstract_submitted');

        $this->actingAs($author, 'author')
            ->get(route('author.submissions.create'))
            ->assertRedirect(PaperResource::getUrl('view', ['record' => $submission], panel: 'author'));

        $this->expectException(QueryException::class);
        $this->submission($edition, $author, 'abstract_submitted');
    }

    public function test_paid_presenter_registration_includes_seminar_access_and_rejects_participant_fee(): void
    {
        $edition = $this->edition();
        $author = $this->author('presenter');
        $presenterFee = $this->fee($edition, 'presenter');
        $participantFee = $this->fee($edition, 'participant');
        $submission = $this->submission($edition, $author, 'accepted');

        $this->actingAs($author, 'author')->post(route('author.registration.store'), [
            'registration_fee_id' => $participantFee->id,
            'submission_id' => $submission->id,
            'payment_method' => 'manual',
        ])->assertSessionHasErrors('registration_fee_id');

        Registration::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'registration_fee_id' => $presenterFee->id,
            'submission_id' => $submission->id,
            'payment_method' => 'manual',
            'amount' => 500000,
            'status' => 'paid',
        ]);

        $this->assertTrue($author->hasSeminarAccess($edition));
        $this->assertDatabaseCount('registrations', 1);
    }

    public function test_author_can_submit_a_valid_abstract_directly(): void
    {
        $edition = $this->edition();
        $author = $this->author('presenter');
        $submission = $this->submission($edition, $author, 'extended_abstract_draft');

        $this->actingAs($author, 'author')->post(route('author.submissions.extended-abstract', $submission), [
            'abstract' => $this->validAbstract(),
        ])->assertRedirect();

        $this->assertSame('extended_abstract_submitted', $submission->refresh()->status);
        $this->assertSame($this->validAbstract(), $submission->abstract);
        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_abstract_below_minimum_words_is_rejected_on_submit(): void
    {
        $edition = $this->edition();
        $author = $this->author('presenter');
        $submission = $this->submission($edition, $author, 'extended_abstract_draft');

        $this->actingAs($author, 'author')->post(route('author.submissions.extended-abstract', $submission), [
            'abstract' => 'This abstract is far too short to qualify.',
        ])->assertSessionHasErrors('abstract');

        $this->assertSame('extended_abstract_draft', $submission->refresh()->status);
    }

    public function test_valid_abstract_has_identical_pdf_access_for_author_and_reviewer(): void
    {
        $edition = $this->edition();
        $author = $this->author('presenter');
        $submission = $this->submission($edition, $author, 'extended_abstract_under_review');
        $reviewer = User::create([
            'name' => 'Abstract Reviewer',
            'email' => 'reviewer@example.test',
            'password' => 'secret-password',
        ]);

        ReviewAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'phase' => 'extended_abstract',
            'assigned_at' => now(),
            'status' => 'pending',
        ]);

        $submission->update([
            'abstract' => $this->validAbstract(),
            'extended_abstract_submitted_at' => now(),
        ]);

        $this->assertTrue($submission->refresh()->hasValidAbstract());

        $authorPdf = $this->actingAs($author, 'author')
            ->get(route('author.submissions.extended-abstract.preview', $submission));
        $authorPdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $authorPdf->getContent());

        $reviewerPdf = $this->actingAs($reviewer, 'web')
            ->get(route('admin.submissions.extended-abstract.preview', $submission));
        $reviewerPdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $reviewerPdf->getContent());
    }

    public function test_author_can_open_the_abstract_editor_immediately_and_it_locks_after_submission(): void
    {
        $edition = $this->edition();
        $author = $this->author('presenter');
        $submission = $this->submission($edition, $author, 'extended_abstract_draft');

        $this->actingAs($author, 'author')
            ->get(PaperResource::getUrl('extended-abstract', ['record' => $submission], panel: 'author'))
            ->assertOk()
            ->assertSee('Edit Submission')
            ->assertSee('Paper information')
            ->assertSee('Authors')
            ->assertSee('Save Changes')
            ->assertSee('Abstract');

        $submission->update([
            'status' => 'extended_abstract_submitted',
            'extended_abstract_submitted_at' => now(),
        ]);

        $this->actingAs($author, 'author')
            ->get(PaperResource::getUrl('extended-abstract', ['record' => $submission], panel: 'author'))
            ->assertForbidden();
    }

    public function test_empty_author_dashboard_shows_the_presenter_journey_with_loa_and_payment(): void
    {
        $this->edition();
        $author = $this->author('presenter');

        $this->actingAs($author, 'author')
            ->get(\App\Filament\Author\Pages\AuthorDashboard::getUrl(panel: 'author'))
            ->assertOk()
            ->assertSee('Create account')
            ->assertSee('Enter abstract')
            ->assertSee('Reviewer verification')
            ->assertSee('Accepted')
            ->assertSee('LOA issued')
            ->assertSee('Payment')
            ->assertDontSee('Abstract review')
            ->assertDontSee('Review passed');
    }

    public function test_presenter_timeline_reaches_reviewer_after_submission(): void
    {
        app()->setLocale('id');
        $edition = $this->edition();
        $author = $this->author('presenter');
        $submission = $this->submission($edition, $author, 'extended_abstract_draft');

        ImportantDate::create([
            'edition_id' => $edition->id,
            'label' => ['id' => 'Batas Extended Abstract', 'en' => 'Extended Abstract Deadline'],
            'date' => today()->addDays(20),
            'is_highlighted' => true,
        ]);

        $journey = app(AuthorJourney::class);
        $submissions = collect([$submission]);
        $registrations = collect();
        $action = $journey->nextAction($author, $submissions, $registrations);
        $timeline = $journey->timeline($author, $submissions, $registrations);

        $this->assertSame('extended', $action['key']);
        $this->assertSame('author', $action['actor']['key']);
        $this->assertCount(7, $timeline);
        $this->assertSame('Kirim full paper', $timeline[6]['label']);
        $this->assertSame('upcoming', $timeline[6]['state']);
        $this->assertSame('Input abstract', $timeline[1]['label']);
        $this->assertSame('current', $timeline[1]['state']);
        $this->assertSame('Verifikasi reviewer', $timeline[2]['label']);
        $this->assertSame('upcoming', $timeline[2]['state']);
        $this->assertFalse($journey->shouldShowPayments($author, $submissions, $registrations));

        $submission->update(['status' => 'extended_abstract_submitted', 'extended_abstract_submitted_at' => now()]);
        $timeline = $journey->timeline($author, collect([$submission->refresh()]), $registrations);
        $this->assertSame('complete', $timeline[1]['state']);
        $this->assertSame('current', $timeline[2]['state']);
        $this->assertSame('reviewer', $timeline[2]['actor']['key']);
    }

    public function test_accepting_a_submission_auto_issues_loa_and_reviewer_drives_sinta3_offer(): void
    {
        $edition = $this->edition();
        $author = $this->author('presenter');
        $submission = $this->submission($edition, $author, 'extended_abstract_under_review');

        $reviewer = User::create(['name' => 'Reviewer', 'email' => 'rev-sinta@example.test', 'password' => 'secret-password']);
        $assignment = ReviewAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'phase' => 'extended_abstract',
            'assigned_at' => now(),
            'status' => 'completed',
        ]);
        \App\Models\Review::create([
            'review_assignment_id' => $assignment->id,
            'score' => 90,
            'recommendation' => 'accept',
            'recommends_sinta3' => true,
            'submitted_at' => now(),
        ]);

        $this->assertNull($submission->loa_issued_at);

        $submission->changeStatus('accepted');
        $submission->refresh();

        // LOA terbit otomatis, dan tawaran SINTA 3 mengikuti rekomendasi reviewer.
        $this->assertNotNull($submission->loa_issued_at);
        $this->assertTrue($submission->sinta3_offered);
        $this->assertTrue($submission->isLoaIssued());
    }

    public function test_full_paper_submission_requires_a_paid_registration(): void
    {
        \Illuminate\Support\Facades\Storage::fake(config('media-library.disk_name'));

        $edition = $this->edition();
        $author = $this->author('presenter');
        $submission = $this->submission($edition, $author, 'accepted');
        $submission->forceFill(['loa_issued_at' => now()])->save();

        // Minimal PDF agar lolos deteksi mime MediaLibrary.
        $pdf = fn () => \Illuminate\Http\UploadedFile::fake()->createWithContent('paper.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

        // Belum ada registrasi lunas → belum boleh kirim full paper.
        $this->assertFalse($submission->canSubmitFullPaper());
        $this->actingAs($author, 'author')
            ->post(route('author.submissions.full-paper', $submission), ['full_paper' => $pdf()])
            ->assertForbidden();

        // Setelah registrasi lunas → boleh.
        $fee = $this->fee($edition, 'presenter');
        Registration::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'registration_fee_id' => $fee->id,
            'submission_id' => $submission->id,
            'payment_method' => 'manual',
            'amount' => 500000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->assertTrue($submission->refresh()->canSubmitFullPaper());
        $this->actingAs($author, 'author')
            ->post(route('author.submissions.full-paper', $submission), ['full_paper' => $pdf()])
            ->assertRedirect();

        $this->assertTrue($submission->refresh()->hasFullPaper());
        $this->assertNotNull($submission->full_paper_submitted_at);
    }

    public function test_registration_form_shows_sinta3_option_and_additional_fee_when_offered(): void
    {
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('author'));

        $edition = $this->edition();
        $author = $this->author('presenter');
        $submission = $this->submission($edition, $author, 'accepted');
        $submission->forceFill(['loa_issued_at' => now(), 'sinta3_offered' => true])->save();
        $this->fee($edition, 'presenter', 'general', 750000);

        $settings = app(\App\Settings\SiteSettings::class);
        $settings->sinta3_fee = 300000;
        $settings->save();

        $this->actingAs($author, 'author');

        Livewire::test(\App\Filament\Author\Resources\Registrations\Pages\CreateRegistration::class)
            ->assertOk()
            ->assertSee('SINTA 3')
            ->assertSee('300.000');
    }

    public function test_recent_updates_are_targeted_to_review_and_payment_status(): void
    {
        app()->setLocale('id');
        $edition = $this->edition();
        $author = $this->author('presenter');
        $submission = $this->submission($edition, $author, 'extended_abstract_draft');
        $fee = $this->fee($edition, 'presenter');
        $registration = Registration::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'registration_fee_id' => $fee->id,
            'submission_id' => $submission->id,
            'payment_method' => 'manual',
            'amount' => 500000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $updates = app(AuthorJourney::class)->recentUpdates(collect([$submission]), collect([$registration]));

        $paymentUpdate = collect($updates)->firstWhere('title', 'Pembayaran terverifikasi');

        $this->assertNotNull($paymentUpdate);
        $this->assertTrue(collect($updates)->contains('title', 'Draft abstract tersedia'));
        $this->assertStringContainsString('akses seminar', $paymentUpdate['description']);
    }

    public function test_loa_is_gated_behind_issuance(): void
    {
        $edition = $this->edition();
        $author = $this->author('presenter');
        $submission = $this->submission($edition, $author, 'accepted');

        // Accepted tetapi LOA belum terbit → LOA tidak dapat dibuka.
        $this->actingAs($author, 'author')
            ->get(route('author.submissions.loa', $submission))
            ->assertNotFound();

        $submission->update(['loa_issued_at' => now()]);
        $this->actingAs($author, 'author')
            ->get(route('author.submissions.loa', $submission))
            ->assertOk();
    }

    public function test_committee_revision_reopens_the_editor_and_surfaces_a_revise_action(): void
    {
        $edition = $this->edition();
        $author = $this->author('presenter');
        $submission = $this->submission($edition, $author, 'extended_abstract_under_review');

        $submission->changeStatus('revision_required');
        $submission->refresh();

        $this->actingAs($author, 'author')
            ->get(PaperResource::getUrl('extended-abstract', ['record' => $submission], panel: 'author'))
            ->assertOk();

        $journey = app(AuthorJourney::class);
        $action = $journey->nextAction($author, collect([$submission]), collect());
        $timeline = $journey->timeline($author, collect([$submission]), collect());
        $this->assertSame('revision', $action['key']);
        $this->assertSame('author', $action['actor']['key']);
        $this->assertSame('current', $timeline[1]['state']);
    }

    public function test_resubmitting_a_revision_resets_reviewers_and_returns_to_review(): void
    {
        $edition = $this->edition();
        $author = $this->author('presenter');
        $topic = Topic::create(['edition_id' => $edition->id, 'title' => ['en' => 'Management'], 'order' => 1]);
        $submission = $this->submission($edition, $author, 'revision_required');
        $submission->update([
            'topic_id' => $topic->id,
            'abstract' => $this->validAbstract(),
        ]);
        $submission->authors()->create(['name' => 'Author One', 'email' => 'a1@example.test', 'is_corresponding' => true, 'order' => 1]);

        $reviewer = User::create(['name' => 'Reviewer', 'email' => 'rev@example.test', 'password' => 'secret-password']);
        $assignment = ReviewAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'phase' => 'extended_abstract',
            'assigned_at' => now(),
            'status' => 'completed',
        ]);

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('author'));
        $this->actingAs($author, 'author');

        Livewire::test(EditExtendedAbstract::class, ['record' => $submission->getRouteKey()])
            ->call('submitForReview');

        $this->assertSame('extended_abstract_under_review', $submission->refresh()->status);
        $this->assertSame('pending', $assignment->refresh()->status);
    }

    private function edition(): Edition
    {
        return Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
    }

    private function author(string $type, string $category = 'general'): Author
    {
        return Author::create([
            'name' => 'Portal User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
            'participation_type' => $type,
            'registrant_category' => $category,
        ]);
    }

    private function fee(Edition $edition, string $audience, string $category = 'general', int $price = 500000): RegistrationFee
    {
        return RegistrationFee::create([
            'edition_id' => $edition->id,
            'category' => ['en' => ucfirst($audience).' '.$category],
            'audience' => $audience,
            'registrant_category' => $category,
            'price_regular' => $price,
            'currency' => 'IDR',
        ]);
    }

    private function submission(Edition $edition, Author $author, string $status): Submission
    {
        return Submission::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'title' => 'A test paper',
            'abstract' => 'A short placeholder abstract.',
            'keywords' => ['management', 'conference'],
            'status' => $status,
        ]);
    }

    /** Abstract 160 kata (valid: 150–500). */
    private function validAbstract(): string
    {
        return implode(' ', array_fill(0, 160, 'management'));
    }
}
