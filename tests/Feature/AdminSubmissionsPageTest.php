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
}
