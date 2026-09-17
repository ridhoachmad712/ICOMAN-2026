<?php

namespace Tests\Feature;

use App\Filament\Widgets\LatestSubmissions;
use App\Filament\Widgets\SubmissionFunnel;
use App\Filament\Widgets\SubmissionWorkboard;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\Submission;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Dashboard admin registrasi.
 *
 * Yang diuji di sini bukan "widgetnya ada", melainkan angkanya benar dan
 * antreannya sama dengan yang dipakai halaman Submissions. Dashboard yang
 * menyebut angka berbeda dari tab di halaman sebelah lebih buruk daripada
 * dashboard yang tidak menyebut angka sama sekali.
 */
class AdminDashboardTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $this->actingAs($this->admin('admin_registrasi'), 'web');
    }

    public function test_the_workboard_counts_what_is_waiting_on_the_committee(): void
    {
        // Menunggu reviewer: dikirim, belum ditugaskan.
        $this->submission('extended_abstract_submitted');

        // Sedang dinilai: reviewer ditugaskan, belum selesai.
        $this->assign($this->submission('extended_abstract_under_review'), 'pending');

        // Menunggu keputusan: penilaian selesai seluruhnya.
        $this->reviewed($this->submission('extended_abstract_under_review'));

        // Diterima tapi LOA belum terbit.
        $this->submission('accepted');

        Livewire::test(SubmissionWorkboard::class)
            ->assertSee('Menunggu Reviewer')
            ->assertSee('Menunggu Keputusan')
            ->assertSee('LOA Belum Terbit');

        $this->assertSame(1, Submission::query()->awaitingReviewer()->count());
        $this->assertSame(1, Submission::query()->underReview()->count());
        $this->assertSame(1, Submission::query()->awaitingDecision()->count());
        $this->assertSame(1, Submission::query()->awaitingLoa()->count());
    }

    /**
     * Papan kerja dan tab di halaman Submissions memakai definisi yang sama.
     * Dulu definisinya ditulis di halaman itu sendiri, jadi tidak ada yang
     * mencegah keduanya berbeda.
     */
    public function test_the_workboard_and_the_needs_action_tab_agree(): void
    {
        $this->submission('extended_abstract_submitted');
        $this->reviewed($this->submission('extended_abstract_under_review'));
        $this->submission('accepted');
        $this->assign($this->submission('extended_abstract_under_review'), 'pending');

        $needsAction = Submission::query()->needsAction()->count();

        $this->assertSame(3, $needsAction, 'Yang sedang dinilai bukan urusan panitia.');
    }

    /** Edition lain tidak boleh ikut terhitung saat ICOMAN berganti tahun. */
    public function test_the_numbers_stay_inside_the_active_edition(): void
    {
        $this->submission('extended_abstract_submitted');

        $old = Edition::create(['name' => 'ICOMAN 2025', 'is_active' => false]);
        $this->submission('extended_abstract_submitted', $old);

        $this->assertSame(2, Submission::query()->awaitingReviewer()->count());
        $this->assertSame(1, Submission::query()->ofCurrentEdition()->awaitingReviewer()->count());
    }

    /**
     * Draft belum pernah dikirim, jadi bukan "abstract terakhir masuk".
     *
     * Penyaringnya harus lewat status: model mengisi `submitted_at` sejak
     * barisnya dibuat, sehingga draft pun punya tanggal kirim.
     */
    public function test_the_latest_table_leaves_out_drafts(): void
    {
        $draft = $this->submission('extended_abstract_draft');
        $sent = $this->submission('extended_abstract_submitted');

        Livewire::test(LatestSubmissions::class)
            ->call('loadTable')
            ->assertSee($sent->submission_number)
            ->assertDontSee($draft->submission_number);
    }

    /** Paper tanpa reviewer harus terbaca begitu, bukan sebagai sel kosong. */
    public function test_the_latest_table_says_when_nobody_is_assigned(): void
    {
        $this->submission('extended_abstract_submitted')->update(['submitted_at' => now()]);

        Livewire::test(LatestSubmissions::class)
            ->call('loadTable')
            ->assertSee('Belum ditugaskan');
    }

    public function test_the_funnel_narrows_from_submitted_to_accepted(): void
    {
        $this->submission('extended_abstract_draft');
        $this->assign($this->submission('extended_abstract_under_review'), 'pending');
        $this->submission('accepted');

        Livewire::test(SubmissionFunnel::class)
            ->assertSee('Alur Submission')
            // 3 masuk (draft tidak dihitung) → 2 diterima? Bukan: 1 diterima.
            ->assertSee('1 dari 2 abstract diterima (50%)');
    }

    /** Content admin tidak punya urusan dengan papan kerja submission. */
    public function test_the_workboard_is_not_for_the_content_admin(): void
    {
        $this->actingAs($this->admin('content_admin'), 'web');

        $this->assertFalse(SubmissionWorkboard::canView());
        $this->assertFalse(LatestSubmissions::canView());
        $this->assertFalse(SubmissionFunnel::canView());
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

    private function submission(string $status, ?Edition $edition = null): Submission
    {
        $author = Author::create([
            'name' => 'Penulis',
            'email' => 'penulis-'.uniqid().'@example.test',
            'password' => 'secret-password',
            'participation_type' => 'presenter',
            'registrant_category' => 'general',
        ]);

        return Submission::create([
            'edition_id' => ($edition ?? $this->edition)->id,
            'author_id' => $author->id,
            'title' => 'Judul',
            'abstract' => str_repeat('kata ', 200),
            'status' => $status,
            'submitted_at' => $status === 'extended_abstract_draft' ? null : now(),
        ]);
    }

    private function assign(Submission $submission, string $status): ReviewAssignment
    {
        return ReviewAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $this->admin('reviewer')->id,
            'phase' => 'extended_abstract',
            'status' => $status,
        ]);
    }

    private function reviewed(Submission $submission): Submission
    {
        $assignment = $this->assign($submission, 'completed');

        Review::create([
            'review_assignment_id' => $assignment->id,
            'score' => 80,
            'recommendation' => 'accept',
        ]);

        return $submission;
    }
}
