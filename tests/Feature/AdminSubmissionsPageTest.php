<?php

namespace Tests\Feature;

use App\Filament\Resources\Submissions\Pages\ListSubmissions;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Submission;
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

    /** Tabel diringkas: nomor pendek, judul & kode penuh disembunyikan — tapi tetap bisa dicari. */
    public function test_list_shows_a_short_number_and_still_searches_by_title_and_code(): void
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

        // Nomor tampil ringkas (#00001); kode ULID penuh hanya jadi tooltip.
        Livewire::test(ListSubmissions::class)
            ->assertSee('#'.str_pad((string) $wanted->id, 5, '0', STR_PAD_LEFT));

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
     * "Koreksi Status" hanya menawarkan koreksi. 'accepted' sengaja TIDAK ada di sini
     * karena menerima paper harus lewat "Keputusan Review" agar LOA terbit; memilihnya
     * dari dropdown generik akan diam-diam menerbitkan LOA + mengirim email.
     */
    public function test_manual_status_correction_excludes_accepted_and_machine_states(): void
    {
        $reflection = new \ReflectionClass(\App\Filament\Resources\Submissions\Tables\SubmissionsTable::class);
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
