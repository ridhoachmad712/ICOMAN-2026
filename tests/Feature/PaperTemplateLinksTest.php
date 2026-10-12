<?php

namespace Tests\Feature;

use App\Filament\Author\Resources\Papers\PaperResource;
use App\Filament\Pages\Settings\ManagePaperTemplates;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Submission;
use App\Models\User;
use App\Settings\SiteSettings;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaperTemplateLinksTest extends TestCase
{
    public function test_admin_can_manage_both_template_links_without_changing_other_site_settings(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('superadmin', 'web');
        $admin = User::create(['name' => 'Admin', 'email' => 'templates-admin@example.test', 'password' => 'secret-password']);
        $admin->assignRole('superadmin');
        $this->actingAs($admin, 'web');

        $conferenceName = app(SiteSettings::class)->conference_name;

        Livewire::test(ManagePaperTemplates::class)
            ->fillForm([
                'paper_template_regular_url' => 'https://example.test/regular.docx',
                'paper_template_sinta3_url' => 'https://example.test/sinta3.docx',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SiteSettings::class)->refresh();
        $this->assertSame('https://example.test/regular.docx', $settings->paper_template_regular_url);
        $this->assertSame('https://example.test/sinta3.docx', $settings->paper_template_sinta3_url);
        $this->assertSame($conferenceName, $settings->conference_name);
    }

    public function test_template_settings_are_limited_to_authorized_admin_roles(): void
    {
        foreach (['superadmin', 'admin_registrasi', 'content_admin', 'reviewer', 'bendahara'] as $role) {
            Role::findOrCreate($role, 'web');
            $user = User::create(['name' => $role, 'email' => $role.'@templates.example.test', 'password' => 'secret-password']);
            $user->assignRole($role);
            $this->actingAs($user, 'web');

            $this->assertSame(
                in_array($role, ['superadmin', 'admin_registrasi', 'content_admin'], true),
                ManagePaperTemplates::canAccess(),
                'Incorrect template settings access for '.$role,
            );
        }
    }

    public function test_author_sees_only_the_template_for_the_selected_journal(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('author'));
        app(SiteSettings::class)->fill([
            'paper_template_regular_url' => 'https://example.test/regular.docx',
            'paper_template_sinta3_url' => 'https://example.test/sinta3.docx',
        ])->save();

        $edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $author = Author::create([
            'name' => 'Presenter', 'email' => 'presenter@templates.example.test',
            'password' => 'secret-password', 'participation_type' => 'presenter',
        ]);
        $submission = Submission::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'title' => 'Template test paper',
            'abstract' => 'Draft abstract',
            'status' => 'accepted',
            'loa_issued_at' => now(),
            'sinta3_offered' => true,
            'journal_target' => 'regular',
            'journal_target_chosen_at' => now(),
        ]);

        $url = PaperResource::getUrl('view', ['record' => $submission], panel: 'author');
        $this->actingAs($author, 'author')
            ->get($url)
            ->assertOk()
            ->assertSee('1. Download the template')
            ->assertSee('2. Upload the final manuscript')
            ->assertSee('File upload becomes available after your registration payment is verified.')
            ->assertSee('https://example.test/regular.docx', false)
            ->assertDontSee('https://example.test/sinta3.docx', false);

        $submission->update(['journal_target' => 'sinta3']);
        $this->actingAs($author, 'author')
            ->get($url)
            ->assertOk()
            ->assertSee('https://example.test/sinta3.docx', false)
            ->assertDontSee('https://example.test/regular.docx', false);

        $submission->update(['journal_target' => 'regular', 'journal_target_chosen_at' => null]);
        $this->actingAs($author, 'author')
            ->get($url)
            ->assertOk()
            ->assertDontSee('https://example.test/regular.docx', false)
            ->assertDontSee('https://example.test/sinta3.docx', false)
            ->assertSee('Choose a journal target');
    }
}
