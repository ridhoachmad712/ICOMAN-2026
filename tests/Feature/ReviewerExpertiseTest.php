<?php

namespace Tests\Feature;

use App\Filament\Resources\Submissions\Pages\ListSubmissions;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Author;
use App\Models\Edition;
use App\Models\ReviewAssignment;
use App\Models\Submission;
use App\Models\Topic;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Penugasan reviewer disaring menurut kepakaran sub-tema.
 *
 * Sebelumnya dialog penugasan menampilkan seluruh reviewer tanpa memandang
 * sub-tema yang dipilih author — dan memang belum ada tempat untuk mencatat
 * kepakaran sama sekali.
 */
class ReviewerExpertiseTest extends TestCase
{
    private Edition $edition;

    private Topic $marketing;

    private Topic $finance;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $this->marketing = Topic::create(['edition_id' => $this->edition->id, 'title' => ['id' => 'Pemasaran', 'en' => 'Marketing'], 'order' => 1]);
        $this->finance = Topic::create(['edition_id' => $this->edition->id, 'title' => ['id' => 'Keuangan', 'en' => 'Finance'], 'order' => 2]);
    }

    private function reviewer(string $name, ?Topic $expertise = null): User
    {
        Role::findOrCreate('reviewer', 'web');
        $user = User::create([
            'name' => $name,
            'email' => Str::slug($name).'@example.test',
            'password' => 'secret-password',
        ]);
        $user->assignRole('reviewer');

        if ($expertise) {
            $user->topics()->attach($expertise);
        }

        return $user;
    }

    private function submission(?Topic $topic = null): Submission
    {
        $author = Author::create([
            'name' => 'Penulis', 'email' => 'penulis-'.uniqid().'@example.test', 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'general',
        ]);

        return Submission::create([
            'edition_id' => $this->edition->id,
            'author_id' => $author->id,
            'topic_id' => $topic?->id,
            'title' => 'Judul',
            'abstract' => str_repeat('kata ', 200),
            'status' => 'extended_abstract_submitted',
        ]);
    }

    private function registrationAdmin(): User
    {
        Role::findOrCreate('admin_registrasi', 'web');
        $admin = User::create(['name' => 'Admin Reg', 'email' => 'adminreg-pakar@example.test', 'password' => 'secret-password']);
        $admin->assignRole('admin_registrasi');
        $this->actingAs($admin, 'web');

        return $admin;
    }

    // --- Penyaringan --------------------------------------------------------

    public function test_only_reviewers_expert_in_the_topic_are_offered(): void
    {
        $marketingExpert = $this->reviewer('Ahli Pemasaran', $this->marketing);
        $financeExpert = $this->reviewer('Ahli Keuangan', $this->finance);

        $offered = User::role('reviewer')->expertIn($this->marketing->id)->pluck('id');

        $this->assertTrue($offered->contains($marketingExpert->id));
        $this->assertFalse($offered->contains($financeExpert->id), 'Reviewer sub-tema lain tidak boleh ikut ditawarkan.');
    }

    /**
     * Reviewer yang kepakarannya belum diisi tetap ditawarkan: saat fitur ini
     * rilis belum ada satu pun yang terisi, dan daftar yang mendadak kosong
     * akan menghentikan seluruh penugasan.
     */
    public function test_reviewers_without_expertise_stay_available_everywhere(): void
    {
        $blank = $this->reviewer('Belum Diisi');

        $this->assertTrue(User::role('reviewer')->expertIn($this->marketing->id)->pluck('id')->contains($blank->id));
        $this->assertTrue(User::role('reviewer')->expertIn($this->finance->id)->pluck('id')->contains($blank->id));
    }

    public function test_a_reviewer_may_cover_several_topics(): void
    {
        $generalist = $this->reviewer('Dua Bidang', $this->marketing);
        $generalist->topics()->attach($this->finance);

        $this->assertTrue(User::role('reviewer')->expertIn($this->marketing->id)->pluck('id')->contains($generalist->id));
        $this->assertTrue(User::role('reviewer')->expertIn($this->finance->id)->pluck('id')->contains($generalist->id));
    }

    /** Paper tanpa sub-tema tidak bisa disaring; tampilkan semuanya. */
    public function test_a_submission_without_a_topic_offers_everyone(): void
    {
        $this->reviewer('Ahli Pemasaran', $this->marketing);
        $this->reviewer('Ahli Keuangan', $this->finance);

        $this->assertSame(2, User::role('reviewer')->expertIn(null)->count());
    }

    /** Hanya user berperan reviewer yang boleh muncul, sepakar apa pun. */
    public function test_non_reviewers_are_never_offered(): void
    {
        Role::findOrCreate('reviewer', 'web');
        Role::findOrCreate('content_admin', 'web');
        $editor = User::create(['name' => 'Editor', 'email' => 'editor-pakar@example.test', 'password' => 'secret-password']);
        $editor->assignRole('content_admin');
        $editor->topics()->attach($this->marketing);

        $this->assertFalse(User::role('reviewer')->expertIn($this->marketing->id)->pluck('id')->contains($editor->id));
    }

    // --- Dialog penugasan ---------------------------------------------------

    public function test_the_dialog_assigns_the_expert(): void
    {
        $this->registrationAdmin();
        $expert = $this->reviewer('Ahli Pemasaran', $this->marketing);
        $this->reviewer('Ahli Keuangan', $this->finance);
        $submission = $this->submission($this->marketing);

        Livewire::test(ListSubmissions::class)
            ->callAction(
                TestAction::make('assignReviewer')->table($submission),
                data: ['reviewer_ids' => [$expert->id]],
            );

        $this->assertDatabaseHas('review_assignments', [
            'submission_id' => $submission->id,
            'reviewer_id' => $expert->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Penyaringannya nyata, bukan sekadar isi dropdown: reviewer sub-tema lain
     * ditolak kalau daftar penuhnya tidak sengaja dibuka.
     */
    public function test_a_reviewer_from_another_topic_is_refused(): void
    {
        $this->registrationAdmin();
        $this->reviewer('Ahli Pemasaran', $this->marketing);
        $outsider = $this->reviewer('Ahli Keuangan', $this->finance);
        $submission = $this->submission($this->marketing);

        Livewire::test(ListSubmissions::class)
            ->callAction(
                TestAction::make('assignReviewer')->table($submission),
                data: ['show_all' => false, 'reviewer_ids' => [$outsider->id]],
            )
            ->assertHasActionErrors(['reviewer_ids.0']);

        $this->assertSame(0, ReviewAssignment::where('submission_id', $submission->id)->count());
    }

    /** Sub-tema tanpa ahli tetap bisa ditugaskan — papernya tidak boleh mandek. */
    public function test_a_topic_without_experts_can_still_be_assigned(): void
    {
        $this->registrationAdmin();
        $other = $this->reviewer('Ahli Keuangan', $this->finance);
        $submission = $this->submission($this->marketing);

        Livewire::test(ListSubmissions::class)
            ->callAction(
                TestAction::make('assignReviewer')->table($submission),
                data: ['show_all' => true, 'reviewer_ids' => [$other->id]],
            );

        $this->assertDatabaseHas('review_assignments', [
            'submission_id' => $submission->id,
            'reviewer_id' => $other->id,
        ]);
    }

    public function test_removing_a_reviewer_drops_the_assignment(): void
    {
        $this->registrationAdmin();
        $first = $this->reviewer('Ahli Pemasaran', $this->marketing);
        $second = $this->reviewer('Ahli Pemasaran Dua', $this->marketing);
        $submission = $this->submission($this->marketing);

        Livewire::test(ListSubmissions::class)->callAction(
            TestAction::make('assignReviewer')->table($submission),
            data: ['reviewer_ids' => [$first->id, $second->id]],
        );
        $this->assertSame(2, ReviewAssignment::where('submission_id', $submission->id)->count());

        // Sesudah ditugaskan, papernya pindah dari tab "Perlu Tindakan".
        Livewire::test(ListSubmissions::class)
            ->set('activeTab', 'all')
            ->callAction(
                TestAction::make('assignReviewer')->table($submission),
                data: ['reviewer_ids' => [$first->id]],
            );

        $this->assertSame(1, ReviewAssignment::where('submission_id', $submission->id)->count());
        $this->assertDatabaseMissing('review_assignments', [
            'submission_id' => $submission->id,
            'reviewer_id' => $second->id,
        ]);
    }

    // --- Mengisi kepakaran di admin -----------------------------------------

    public function test_expertise_is_saved_from_the_user_form(): void
    {
        Role::findOrCreate('superadmin', 'web');
        $admin = User::create(['name' => 'Super', 'email' => 'super-pakar@example.test', 'password' => 'secret-password']);
        $admin->assignRole('superadmin');
        $this->actingAs($admin, 'web');

        $reviewer = $this->reviewer('Calon Pakar');

        Livewire::test(EditUser::class, ['record' => $reviewer->getRouteKey()])
            ->assertOk()
            ->fillForm(['topics' => [$this->marketing->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($reviewer->refresh()->topics->contains($this->marketing));
    }

    /** Menghapus sub-tema tidak boleh menyisakan kepakaran menggantung. */
    public function test_deleting_a_topic_clears_the_expertise(): void
    {
        $reviewer = $this->reviewer('Ahli Pemasaran', $this->marketing);

        $this->marketing->delete();

        $this->assertSame(0, $reviewer->refresh()->topics()->count());
    }
}
