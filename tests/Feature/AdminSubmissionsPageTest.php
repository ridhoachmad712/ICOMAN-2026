<?php

namespace Tests\Feature;

use App\Filament\Resources\Submissions\Pages\EditSubmission;
use App\Filament\Resources\Submissions\Pages\ListSubmissions;
use App\Filament\Resources\Submissions\Tables\SubmissionsTable;
use App\Models\Author;
use App\Models\Edition;
use App\Models\ReviewAssignment;
use App\Models\Submission;
use App\Models\SubmissionAuthor;
use App\Models\Topic;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSubmissionsPageTest extends TestCase
{
    /**
     * Halaman Submissions dirombak: satu aksi utama + menu "Lainnya", dan tab
     * mengikuti alur kerja. Tes ini menjaga halaman tetap render dan tab-nya utuh.
     */
    public function test_submissions_list_renders_with_workflow_tabs(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('superadmin', 'web');

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret-password',
        ]);
        $admin->assignRole('superadmin');

        $edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $author = Author::create([
            'name' => 'Presenter',
            'email' => 'presenter@example.test',
            'password' => 'secret-password',
            'participation_type' => 'presenter',
        ]);
        Submission::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'title' => 'A paper',
            'abstract' => str_repeat('word ', 200),
            'status' => 'extended_abstract_submitted',
        ]);

        $this->actingAs($admin, 'web');

        $page = Livewire::test(ListSubmissions::class)->assertOk();

        $tabs = array_keys($page->instance()->getTabs());
        $this->assertSame(['action', 'under_review', 'accepted', 'rejected', 'all'], $tabs);
    }

    /** Tabel diringkas: kode submission tampil, judul disembunyikan — tapi tetap bisa dicari. */
    public function test_list_shows_the_submission_code_and_still_searches_by_title_and_code(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('superadmin', 'web');

        $admin = User::create(['name' => 'Admin', 'email' => 'admin2@example.test', 'password' => 'secret-password']);
        $admin->assignRole('superadmin');

        $edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $author = Author::create([
            'name' => 'Presenter',
            'email' => 'presenter2@example.test',
            'password' => 'secret-password',
            'participation_type' => 'presenter',
        ]);
        $wanted = Submission::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'title' => 'Unique Marketing Study',
            'abstract' => str_repeat('word ', 200),
            'status' => 'extended_abstract_submitted',
        ]);
        // Satu author hanya boleh punya satu paper per edisi.
        $author2 = Author::create([
            'name' => 'Presenter Dua',
            'email' => 'presenter3@example.test',
            'password' => 'secret-password',
            'participation_type' => 'presenter',
        ]);
        $other = Submission::create([
            'edition_id' => $edition->id,
            'author_id' => $author2->id,
            'title' => 'Something Else Entirely',
            'abstract' => str_repeat('word ', 200),
            'status' => 'extended_abstract_submitted',
        ]);

        $this->actingAs($admin, 'web');

        // Kode submission kini pendek, jadi ditampilkan apa adanya — admin dan
        // author menyebut nomor yang sama.
        Livewire::test(ListSubmissions::class)
            ->assertSee($wanted->submission_number);

        // Pencarian judul tetap bekerja walau kolom judul disembunyikan.
        Livewire::test(ListSubmissions::class)
            ->searchTable('Unique Marketing')
            ->assertCanSeeTableRecords([$wanted])
            ->assertCanNotSeeTableRecords([$other]);

        // Pencarian dengan kode submission penuh juga tetap bekerja.
        Livewire::test(ListSubmissions::class)
            ->searchTable($wanted->submission_number)
            ->assertCanSeeTableRecords([$wanted])
            ->assertCanNotSeeTableRecords([$other]);
    }

    /**
     * Halaman detail menaruh naskah di kolom lebar dan keterangan pendek di
     * kolom sempit. Tesnya menjaga isi tiap sisi tetap sampai ke halaman —
     * termasuk status yang dibaca manusia, bukan nama status mentahnya.
     */
    public function test_the_detail_page_shows_both_columns(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('reviewer', 'web');

        $admin = User::create(['name' => 'Admin', 'email' => 'admin-detail@example.test', 'password' => 'secret-password']);
        $admin->assignRole('superadmin');
        $reviewer = User::create(['name' => 'Penilai Satu', 'email' => 'penilai@example.test', 'password' => 'secret-password']);
        $reviewer->assignRole('reviewer');

        $edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $topic = Topic::create(['edition_id' => $edition->id, 'title' => ['id' => 'Pemasaran', 'en' => 'Marketing'], 'order' => 1]);
        $author = Author::create([
            'name' => 'Presenter Detail', 'email' => 'presenter-detail@example.test',
            'password' => 'secret-password', 'participation_type' => 'presenter',
        ]);
        $submission = Submission::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'topic_id' => $topic->id,
            'title' => 'Judul Paper Yang Panjang',
            'abstract' => str_repeat('kata ', 200),
            'keywords' => ['Content Quality', 'Brand Awareness'],
            'status' => 'extended_abstract_under_review',
            'extended_abstract_submitted_at' => now(),
        ]);
        SubmissionAuthor::create([
            'submission_id' => $submission->id,
            'name' => 'Presenter Detail',
            'email' => 'presenter-detail@example.test',
            'affiliation' => 'Universitas Contoh',
            'is_corresponding' => true,
            'order' => 1,
        ]);
        ReviewAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'phase' => 'extended_abstract',
            'assigned_at' => now(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'web');

        Livewire::test(EditSubmission::class, ['record' => $submission->getRouteKey()])
            ->assertOk()
            // Kolom lebar: naskahnya.
            ->assertSee('Judul Paper Yang Panjang')
            ->assertSee('Content Quality')
            ->assertSee('Extended Abstract')
            // Kolom sempit: keterangan pendeknya.
            ->assertSee($submission->submission_number)
            ->assertSee('Verifikasi reviewer')
            ->assertDontSee('extended_abstract_under_review')
            ->assertSee($topic->title)
            ->assertSee('Presenter Detail')
            ->assertSee('Penilai Satu');
    }

    /**
     * "Koreksi Status" hanya menawarkan koreksi. 'accepted' sengaja TIDAK ada di sini
     * karena menerima paper harus lewat "Keputusan Review" agar LOA terbit; memilihnya
     * dari dropdown generik akan diam-diam menerbitkan LOA + mengirim email.
     */
    public function test_manual_status_correction_excludes_accepted_and_machine_states(): void
    {
        $reflection = new \ReflectionClass(SubmissionsTable::class);
        $options = $reflection->getConstant('MANUAL_STATUS_OPTIONS');

        $this->assertSame(
            ['extended_abstract_submitted', 'revision_required', 'rejected'],
            array_keys($options),
        );
        $this->assertArrayNotHasKey('accepted', $options);
        $this->assertArrayNotHasKey('extended_abstract_draft', $options);
        $this->assertArrayNotHasKey('extended_abstract_under_review', $options);
    }
}
