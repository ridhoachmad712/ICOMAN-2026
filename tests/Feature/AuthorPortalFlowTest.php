<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Edition;
use App\Models\ImportantDate;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\ReviewAssignment;
use App\Models\Submission;
use App\Models\User;
use App\Models\Topic;
use App\Services\AuthorJourney;
use App\Services\ExtendedAbstractDocument;
use App\Filament\Author\Resources\Papers\PaperResource;
use App\Filament\Author\Resources\Papers\Pages\EditExtendedAbstract;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AuthorPortalFlowTest extends TestCase
{
    public function test_registration_choice_is_persisted_on_the_author_account(): void
    {
        $this->post(route('author.register'), [
            'name' => 'Seminar Participant',
            'email' => 'participant@example.test',
            'participation_type' => 'non_presenter',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])->assertRedirect(\App\Filament\Author\Resources\Registrations\RegistrationResource::getUrl('create', panel: 'author'));

        $this->assertDatabaseHas('authors', [
            'email' => 'participant@example.test',
            'participation_type' => 'participant',
        ]);
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

    public function test_presenter_registration_is_linked_to_an_approved_abstract_and_cannot_be_duplicated(): void
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

        $this->actingAs($author, 'author')->post(route('author.registration.store'), $payload)->assertRedirect();
        $registration = Registration::firstOrFail();
        $this->assertSame($submission->id, $registration->submission_id);

        $this->actingAs($author, 'author')
            ->post(route('author.registration.store'), $payload)
            ->assertRedirect(route('author.registration.show', $registration));
        $this->assertDatabaseCount('registrations', 1);
    }

    public function test_presenter_can_submit_only_one_abstract_per_edition(): void
    {
        $edition = $this->edition();
        $author = $this->author('presenter');
        $submission = $this->submission($edition, $author, 'abstract_submitted');

        $this->actingAs($author, 'author')
            ->get(route('author.submissions.create'))
            ->assertRedirect(\App\Filament\Author\Resources\Papers\PaperResource::getUrl('view', ['record' => $submission], panel: 'author'));

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

    public function test_author_can_submit_extended_abstract_directly_without_payment(): void
    {
        $edition = $this->edition();
        $author = $this->author('presenter');
        $submission = $this->submission($edition, $author, 'extended_abstract_draft');

        $this->actingAs($author, 'author')->post(route('author.submissions.extended-abstract', $submission), [
            'extended_abstract' => 'Extended abstract content.',
        ])->assertRedirect();

        $this->assertSame('extended_abstract_submitted', $submission->refresh()->status);
        $this->assertSame('Extended abstract content.', $submission->extended_abstract);
        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_structured_extended_abstract_is_complete_and_has_identical_pdf_access_for_author_and_reviewer(): void
    {
        $edition = $this->edition();
        $author = $this->author('presenter');
        $submission = $this->submission($edition, $author, 'extended_abstract_under_review');
        $reviewer = User::create([
            'name' => 'Extended Abstract Reviewer',
            'email' => 'extended-reviewer@example.test',
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
            'extended_abstract_abstract' => $this->richText('This is the abstract.', 'E = mc^2'),
            'extended_abstract_introduction' => $this->richText('This is the introduction.'),
            'extended_abstract_method' => $this->richText('This is the method.'),
            'extended_abstract_results_discussion' => $this->richText('These are the results and discussion.'),
            'extended_abstract_conclusion' => $this->richText('This is the conclusion.'),
            'extended_abstract_draft_saved_at' => now(),
            'extended_abstract_submitted_at' => now(),
        ]);

        $this->assertTrue($submission->refresh()->hasCompleteExtendedAbstract());
        $this->assertStringContainsString('E &#61; mc^2', $submission->renderRichContent('extended_abstract_abstract'));

        $authorPdf = $this->actingAs($author, 'author')
            ->get(route('author.submissions.extended-abstract.preview', $submission));
        $authorPdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $authorPdf->getContent());

        $reviewerPdf = $this->actingAs($reviewer, 'web')
            ->get(route('admin.submissions.extended-abstract.preview', $submission));
        $reviewerPdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $reviewerPdf->getContent());
    }

    public function test_author_can_open_the_structured_extended_abstract_editor_immediately(): void
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
            ->assertSee('Abstract')
            ->assertSee('Introduction')
            ->assertSee('Results and Discussion')
            ->assertSee('Conclusion');

        $submission->update([
            'status' => 'extended_abstract_submitted',
            'extended_abstract_submitted_at' => now(),
        ]);

        $this->actingAs($author, 'author')
            ->get(PaperResource::getUrl('extended-abstract', ['record' => $submission], panel: 'author'))
            ->assertForbidden();
    }

    public function test_empty_author_dashboard_shows_the_five_step_presenter_journey(): void
    {
        $this->edition();
        $author = $this->author('presenter');

        $this->actingAs($author, 'author')
            ->get(\App\Filament\Author\Pages\AuthorDashboard::getUrl(panel: 'author'))
            ->assertOk()
            ->assertSee('Create account')
            ->assertSee('Enter extended abstract')
            ->assertSee('Reviewer verification')
            ->assertSee('Accepted')
            ->assertSee('Payment')
            ->assertDontSee('Abstract review')
            ->assertDontSee('Review passed');

        $this->actingAs($author, 'author')
            ->get(PaperResource::getUrl('create', panel: 'author'))
            ->assertOk()
            ->assertSee('Start Extended Abstract')
            ->assertSee('Paper details')
            ->assertDontSee('Abstract (English)');
    }

    public function test_author_journey_goes_directly_from_extended_abstract_to_reviewer(): void
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
        $this->assertSame('Batas Extended Abstract', $action['deadline']['label']);
        $this->assertSame(20, $action['deadline']['days']);
        $this->assertCount(5, $timeline);
        $this->assertSame('Input extended abstract', $timeline[1]['label']);
        $this->assertSame('current', $timeline[1]['state']);
        $this->assertSame('author', $timeline[1]['actor']['key']);
        $this->assertSame('Verifikasi reviewer', $timeline[2]['label']);
        $this->assertSame('upcoming', $timeline[2]['state']);
        $this->assertFalse($journey->shouldShowPayments($author, $submissions, $registrations));

        $submission->update(['status' => 'extended_abstract_submitted', 'extended_abstract_submitted_at' => now()]);
        $timeline = $journey->timeline($author, collect([$submission->refresh()]), $registrations);
        $this->assertSame('complete', $timeline[1]['state']);
        $this->assertSame('current', $timeline[2]['state']);
        $this->assertSame('reviewer', $timeline[2]['actor']['key']);
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
        $this->assertTrue(collect($updates)->contains('title', 'Draft extended abstract tersedia'));
        $this->assertStringContainsString('akses seminar', $paymentUpdate['description']);
    }

    public function test_committee_revision_reopens_the_editor_and_surfaces_a_revise_action(): void
    {
        $edition = $this->edition();
        $author = $this->author('presenter');
        $submission = $this->submission($edition, $author, 'extended_abstract_under_review');

        // Panitia meminta revisi.
        $submission->changeStatus('revision_required');
        $submission->refresh();

        // Editor terbuka kembali untuk author (canEdit true → 200, bukan 403).
        $this->actingAs($author, 'author')
            ->get(PaperResource::getUrl('extended-abstract', ['record' => $submission], panel: 'author'))
            ->assertOk();

        // Journey menampilkan aksi "revise" ke author, dan langkah input aktif lagi.
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
            'extended_abstract_abstract' => $this->richText('Revised abstract.'),
            'extended_abstract_introduction' => $this->richText('Revised introduction.'),
            'extended_abstract_method' => $this->richText('Revised method.'),
            'extended_abstract_results_discussion' => $this->richText('Revised results.'),
            'extended_abstract_conclusion' => $this->richText('Revised conclusion.'),
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

    public function test_extended_abstract_pdf_only_embeds_images_owned_by_the_submission(): void
    {
        Storage::fake('local');
        $edition = $this->edition();
        $author = $this->author('presenter');
        $other = $this->author('presenter');

        $mine = $this->submission($edition, $author, 'extended_abstract_draft');
        $victim = $this->submission($edition, $other, 'extended_abstract_draft');

        Storage::disk('local')->put("submissions/{$mine->id}/extended-abstract/own.png", 'OWN-IMAGE-BYTES');
        Storage::disk('local')->put("submissions/{$victim->id}/extended-abstract/secret.png", 'SECRET-VICTIM-BYTES');

        // Author menyisipkan satu gambar miliknya dan satu gambar milik submission lain.
        $mine->update(['extended_abstract_abstract' => ['type' => 'doc', 'content' => [
            ['type' => 'image', 'attrs' => ['id' => "submissions/{$mine->id}/extended-abstract/own.png"]],
            ['type' => 'image', 'attrs' => ['id' => "submissions/{$victim->id}/extended-abstract/secret.png"]],
        ]]]);

        $sections = app(ExtendedAbstractDocument::class)->sections($mine->refresh(), true);
        $html = $sections['extended_abstract_abstract']['html'];

        $this->assertStringContainsString(base64_encode('OWN-IMAGE-BYTES'), $html);
        $this->assertStringNotContainsString(base64_encode('SECRET-VICTIM-BYTES'), $html);
    }

    private function edition(): Edition
    {
        return Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
    }

    private function author(string $type): Author
    {
        return Author::create([
            'name' => 'Portal User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
            'participation_type' => $type,
        ]);
    }

    private function fee(Edition $edition, string $audience): RegistrationFee
    {
        return RegistrationFee::create([
            'edition_id' => $edition->id,
            'category' => ['en' => ucfirst($audience)],
            'audience' => $audience,
            'price_regular' => 500000,
            'currency' => 'IDR',
        ]);
    }

    private function submission(Edition $edition, Author $author, string $status): Submission
    {
        return Submission::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'title' => 'A test paper',
            'abstract' => 'A sufficient test abstract.',
            'keywords' => ['management', 'conference'],
            'status' => $status,
        ]);
    }

    private function richText(string $text, ?string $equation = null): array
    {
        $content = [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => $text]],
        ]];

        if ($equation) {
            $content[] = [
                'type' => 'customBlock',
                'attrs' => [
                    'id' => 'equation',
                    'config' => ['latex' => $equation, 'display' => 'block'],
                ],
            ];
        }

        return ['type' => 'doc', 'content' => $content];
    }
}
