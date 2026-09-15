<?php

namespace Tests\Feature;

use App\Filament\Resources\ReviewAssignments\Pages\AssessSubmission;
use App\Filament\Resources\ReviewAssignments\ReviewAssignmentResource;
use App\Filament\Widgets\MyPendingReviews;
use App\Models\Author;
use App\Models\Edition;
use App\Models\ImportantDate;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\Submission;
use App\Models\SubmissionAuthor;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Meja kerja reviewer.
 *
 * Dashboard reviewer dulu hanya dua angka yang tidak bisa diklik, dan penilaian
 * dijejalkan ke satu modal bersama seluruh naskah. Sekarang dashboard langsung
 * menampilkan pekerjaannya, penilaian punya halaman sendiri, dan identitas
 * penulis disembunyikan dari reviewer.
 */
class ReviewerWorkspaceTest extends TestCase
{
    private Edition $edition;

    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);

        $author = Author::create([
            'name' => 'Penulis Rahasia', 'email' => 'rahasia@example.test', 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'general',
        ]);

        $this->submission = Submission::create([
            'edition_id' => $this->edition->id,
            'author_id' => $author->id,
            'title' => 'Judul Paper Uji',
            'abstract' => str_repeat('kata ', 200),
            'status' => 'extended_abstract_under_review',
        ]);

        SubmissionAuthor::create([
            'submission_id' => $this->submission->id,
            'name' => 'Penulis Rahasia',
            'email' => 'rahasia@example.test',
            'affiliation' => 'Universitas Rahasia',
            'is_corresponding' => true,
            'order' => 1,
        ]);
    }

    private function reviewer(string $email = 'reviewer@example.test'): User
    {
        Role::findOrCreate('reviewer', 'web');
        $user = User::create(['name' => 'Reviewer', 'email' => $email, 'password' => 'secret-password']);
        $user->assignRole('reviewer');
        $this->actingAs($user, 'web');

        return $user;
    }

    private function assignment(User $reviewer, string $status = 'pending'): ReviewAssignment
    {
        return ReviewAssignment::create([
            'submission_id' => $this->submission->id,
            'reviewer_id' => $reviewer->id,
            'phase' => 'extended_abstract',
            'status' => $status,
            'assigned_at' => now(),
        ]);
    }

    // --- Meja kerja di dashboard ------------------------------------------

    public function test_the_dashboard_lists_the_work_waiting_for_the_reviewer(): void
    {
        $reviewer = $this->reviewer();
        $assignment = $this->assignment($reviewer);

        Livewire::test(MyPendingReviews::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$assignment])
            ->assertSee('Judul Paper Uji');
    }

    /** Pekerjaan reviewer lain tidak boleh muncul di mejanya. */
    public function test_the_desk_only_shows_your_own_assignments(): void
    {
        $other = $this->reviewer('lain@example.test');
        $otherAssignment = $this->assignment($other);

        $mine = $this->reviewer('saya@example.test');
        $myAssignment = $this->assignment($mine);

        Livewire::test(MyPendingReviews::class)
            ->assertCanSeeTableRecords([$myAssignment])
            ->assertCanNotSeeTableRecords([$otherAssignment]);
    }

    /** Yang sudah dinilai tidak lagi menuntut perhatian di meja kerja. */
    public function test_completed_assignments_leave_the_desk(): void
    {
        $reviewer = $this->reviewer();
        $done = $this->assignment($reviewer, 'completed');

        Livewire::test(MyPendingReviews::class)->assertCanNotSeeTableRecords([$done]);
    }

    public function test_the_desk_is_only_for_reviewers(): void
    {
        Role::findOrCreate('admin_registrasi', 'web');
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-desk@example.test', 'password' => 'secret-password']);
        $admin->assignRole('admin_registrasi');
        $this->actingAs($admin, 'web');

        $this->assertFalse(MyPendingReviews::canView());
    }

    // --- Halaman penilaian -------------------------------------------------

    public function test_the_reviewer_can_save_an_assessment_from_its_own_page(): void
    {
        $reviewer = $this->reviewer();
        $assignment = $this->assignment($reviewer);

        Livewire::test(AssessSubmission::class, ['record' => $assignment->id])
            ->assertOk()
            ->fillForm([
                'score' => 82,
                'recommendation' => 'minor_revision',
                'recommends_sinta3' => true,
                'comments_for_author' => 'Perjelas metodenya.',
                'comments_for_committee' => 'Layak lanjut.',
            ])
            ->call('save');

        $review = Review::firstWhere('review_assignment_id', $assignment->id);

        $this->assertNotNull($review);
        $this->assertSame(82, (int) $review->score);
        $this->assertSame('minor_revision', $review->recommendation);
        $this->assertTrue((bool) $review->recommends_sinta3);
        $this->assertSame('completed', $assignment->refresh()->status);
    }

    /** Rekomendasi wajib: penilaian tanpa kesimpulan tidak berguna bagi panitia. */
    public function test_an_assessment_without_a_recommendation_is_refused(): void
    {
        $reviewer = $this->reviewer();
        $assignment = $this->assignment($reviewer);

        Livewire::test(AssessSubmission::class, ['record' => $assignment->id])
            ->fillForm(['score' => 70])
            ->call('save')
            ->assertHasFormErrors(['recommendation']);

        $this->assertSame('pending', $assignment->refresh()->status);
    }

    /**
     * Reviewer lain tidak boleh membuka, apalagi menilai, penugasan orang.
     * Jawabannya 404, bukan 403: daftar penugasan sudah disaring per reviewer,
     * jadi keberadaan penugasan itu pun tidak diakui.
     */
    public function test_another_reviewer_cannot_open_the_assessment(): void
    {
        $owner = $this->reviewer('pemilik@example.test');
        $assignment = $this->assignment($owner);

        $this->reviewer('penyusup@example.test');

        $this->get(ReviewAssignmentResource::getUrl('assess', ['record' => $assignment]))
            ->assertNotFound();

        $this->assertSame('pending', $assignment->refresh()->status);
    }

    // --- Review buta --------------------------------------------------------

    public function test_the_assessment_page_hides_the_author_identity(): void
    {
        $reviewer = $this->reviewer();
        $assignment = $this->assignment($reviewer);

        $html = $this->get(ReviewAssignmentResource::getUrl('assess', ['record' => $assignment]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Penulis Rahasia', $html);
        $this->assertStringContainsString('Judul Paper Uji', $html);
    }

    /** PDF yang dibuka reviewer juga tidak boleh memuat nama dan afiliasi penulis. */
    public function test_the_pdf_a_reviewer_opens_is_blind(): void
    {
        $reviewer = $this->reviewer();
        $this->assignment($reviewer);

        $pdf = $this->get(route('admin.submissions.extended-abstract.preview', $this->submission))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Penulis Rahasia', $pdf);
        $this->assertStringNotContainsString('Universitas Rahasia', $pdf);
    }

    /** Panitia tetap melihat versi lengkap. */
    public function test_the_committee_still_sees_the_authors(): void
    {
        Role::findOrCreate('superadmin', 'web');
        $admin = User::create(['name' => 'Super', 'email' => 'super-blind@example.test', 'password' => 'secret-password']);
        $admin->assignRole('superadmin');
        $this->actingAs($admin, 'web');

        $html = view('pdf.extended-abstract', [
            'submission' => $this->submission->fresh(['authors', 'edition', 'topic']),
            'blind' => false,
        ])->render();

        $this->assertStringContainsString('Penulis Rahasia', $html);
    }

    /** Sisa hari ditampilkan bulat, bukan pecahan seperti "25.3077 hari lagi". */
    public function test_the_deadline_countdown_is_a_whole_number(): void
    {
        app()->setLocale('en');
        $reviewer = $this->reviewer();
        $this->assignment($reviewer);

        ImportantDate::create([
            'edition_id' => $this->edition->id,
            'label' => ['id' => 'Pengumuman', 'en' => 'Acceptance'],
            'kind' => 'acceptance',
            'date' => now()->addDays(10),
            'closes_at' => now()->addDays(10),
            'order' => 1,
        ]);

        $html = Livewire::test(MyPendingReviews::class)->assertOk()->html();

        $this->assertMatchesRegularExpression('/\(\d+ '.preg_quote(__('review.desk_days_left'), '/').'\)/', $html);
        $this->assertStringNotContainsString('.', substr($html, strpos($html, __('review.desk_deadline')), 120));
    }
}
