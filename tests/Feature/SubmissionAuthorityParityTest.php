<?php

namespace Tests\Feature;

use App\Filament\Resources\Submissions\Pages\ListSubmissions;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\Submission;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Superadmin dan admin registrasi melihat halaman Submissions yang sama.
 *
 * Keduanya sama-sama bisa membuka halaman ini, tapi empat tombolnya dulu
 * superadmin saja: Keputusan Review, Terbitkan LOA, tawaran SINTA 3, dan
 * Review Langsung. Admin registrasi karena itu membuka halaman yang secara
 * diam-diam lebih sempit — tanpa pesan apa pun, hanya tombol yang tidak ada,
 * sehingga tampak seperti fitur yang rusak.
 *
 * Panitia memutuskan keduanya sejajar. Tes ini membandingkan apa yang benar-
 * benar terlihat oleh masing-masing peran, bukan sekadar memanggil methodnya.
 */
class SubmissionAuthorityParityTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
    }

    public function test_both_roles_may_decide_a_reviewed_paper(): void
    {
        $submission = $this->reviewedSubmission();

        foreach (['superadmin', 'admin_registrasi'] as $role) {
            $this->actingAs($this->admin($role), 'web');

            Livewire::test(ListSubmissions::class)
                ->set('activeTab', 'all')
                ->assertActionVisible(TestAction::make('decision')->table($submission));
        }
    }

    public function test_both_roles_see_the_loa_and_sinta3_buttons_on_an_accepted_paper(): void
    {
        $submission = $this->reviewedSubmission();
        $submission->update(['status' => 'accepted', 'loa_issued_at' => null]);

        foreach (['superadmin', 'admin_registrasi'] as $role) {
            $this->actingAs($this->admin($role), 'web');

            Livewire::test(ListSubmissions::class)
                ->set('activeTab', 'all')
                ->assertActionVisible(TestAction::make('issueLoa')->table($submission))
                ->assertActionVisible(TestAction::make('sinta3Offer')->table($submission));
        }
    }

    public function test_both_roles_may_review_a_paper_directly(): void
    {
        $submission = $this->submission();

        foreach (['superadmin', 'admin_registrasi'] as $role) {
            $this->actingAs($this->admin($role), 'web');

            Livewire::test(ListSubmissions::class)
                ->set('activeTab', 'all')
                ->assertActionVisible(TestAction::make('reviewDirectly')->table($submission));
        }
    }

    /**
     * Kesejajaran ini berlaku untuk dua peran itu saja. Content admin dan
     * reviewer tetap tidak punya urusan dengan halaman Submissions.
     */
    public function test_the_other_roles_still_cannot_reach_the_page(): void
    {
        foreach (['content_admin', 'reviewer'] as $role) {
            $this->assertFalse($this->admin($role)->managesSubmissions(), $role.' tidak boleh menangani Submissions.');
        }

        foreach (['superadmin', 'admin_registrasi'] as $role) {
            $this->assertTrue($this->admin($role)->managesSubmissions());
        }
    }

    /** Keputusan yang diambil admin registrasi benar-benar tersimpan. */
    public function test_a_decision_taken_by_the_registration_admin_sticks(): void
    {
        $submission = $this->reviewedSubmission();
        $this->actingAs($this->admin('admin_registrasi'), 'web');

        Livewire::test(ListSubmissions::class)
            ->set('activeTab', 'all')
            ->callAction(
                TestAction::make('decision')->table($submission),
                data: ['status' => 'accepted'],
            );

        $this->assertSame('accepted', $submission->refresh()->status);
    }

    private function admin(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::create([
            'name' => ucfirst($role),
            'email' => $role.'-'.uniqid().'@example.test',
            'password' => 'secret-password',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function submission(): Submission
    {
        $author = Author::create([
            'name' => 'Penulis',
            'email' => 'penulis-'.uniqid().'@example.test',
            'password' => 'secret-password',
            'participation_type' => 'presenter',
            'registrant_category' => 'general',
        ]);

        return Submission::create([
            'edition_id' => $this->edition->id,
            'author_id' => $author->id,
            'title' => 'Judul',
            'abstract' => str_repeat('kata ', 200),
            'status' => 'extended_abstract_submitted',
        ]);
    }

    /** Paper yang reviewnya sudah selesai, sehingga tombol keputusan berlaku. */
    private function reviewedSubmission(): Submission
    {
        $submission = $this->submission();
        $reviewer = $this->admin('reviewer');

        $assignment = ReviewAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'phase' => $submission->currentReviewPhase(),
            'status' => 'completed',
        ]);

        Review::create([
            'review_assignment_id' => $assignment->id,
            'score' => 80,
            'recommendation' => 'accept',
        ]);

        return $submission->refresh();
    }
}
