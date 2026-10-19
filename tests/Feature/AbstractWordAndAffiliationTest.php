<?php

namespace Tests\Feature;

use App\Filament\Author\Pages\AuthorDashboard;
use App\Filament\Widgets\LatestSubmissions;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Submission;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

/**
 * Panitia mengunduh abstrak yang diterima sebagai Word untuk menyusun book of
 * abstracts, dan afiliasi pengirim terlihat di dashboard admin maupun author.
 */
class AbstractWordAndAffiliationTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
    }

    public function test_submission_managers_download_an_accepted_abstract_as_word(): void
    {
        $submission = $this->submission('accepted');
        $submission->authors()->create([
            'name' => 'Rina Saputri', 'email' => 'rina@example.test',
            'affiliation' => 'Universitas Negeri Makassar', 'is_corresponding' => true, 'order' => 1,
        ]);

        foreach (['superadmin', 'admin_registrasi'] as $role) {
            $response = $this->actingAs($this->user($role), 'web')
                ->get(route('admin.submissions.abstract-word', $submission))
                ->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
                ->assertDownload($submission->submission_number.'-abstract.docx');

            $xml = $this->documentXml($response->baseResponse->getFile()->getPathname());

            $this->assertNotFalse(simplexml_load_string($xml), 'document.xml harus XML yang valid.');
            $this->assertStringContainsString('Dampak AI &amp; Ketahanan Bisnis', $xml);
            $this->assertStringContainsString('Rina Saputri', $xml);
            $this->assertStringContainsString('Universitas Negeri Makassar', $xml);
            $this->assertStringContainsString('management management', $xml);
            $this->assertStringContainsString('Keywords: ', $xml);
        }
    }

    public function test_other_roles_and_unaccepted_abstracts_get_no_word_file(): void
    {
        $accepted = $this->submission('accepted');

        foreach (['reviewer', 'bendahara', 'content_admin'] as $role) {
            $this->actingAs($this->user($role), 'web')
                ->get(route('admin.submissions.abstract-word', $accepted))
                ->assertForbidden();
        }

        $this->actingAs($this->user('superadmin'), 'web')
            ->get(route('admin.submissions.abstract-word', $this->submission('extended_abstract_under_review')))
            ->assertNotFound();
    }

    public function test_admin_dashboard_shows_the_submitter_affiliation(): void
    {
        $this->actingAs($this->user('admin_registrasi'), 'web');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->submission('extended_abstract_submitted');

        Livewire::test(LatestSubmissions::class)
            ->call('loadTable')
            ->assertSee('Universitas Hasanuddin');
    }

    public function test_author_dashboard_shows_the_author_affiliation(): void
    {
        $author = $this->author();

        $this->actingAs($author, 'author')
            ->get(AuthorDashboard::getUrl(panel: 'author'))
            ->assertOk()
            ->assertSee('Universitas Hasanuddin');
    }

    private function documentXml(string $path): string
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'Berkas bukan arsip .docx yang valid.');
        $xml = $zip->getFromName('word/document.xml');
        $this->assertNotFalse($zip->getFromName('[Content_Types].xml'));
        $zip->close();

        return (string) $xml;
    }

    private function submission(string $status): Submission
    {
        return Submission::create([
            'edition_id' => $this->edition->id,
            'author_id' => $this->author()->id,
            'title' => 'Dampak AI & Ketahanan Bisnis',
            'abstract' => implode(' ', array_fill(0, 220, 'management')),
            'keywords' => ['management', 'resilience'],
            'status' => $status,
        ]);
    }

    private function author(): Author
    {
        return Author::create([
            'name' => 'Portal User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
            'affiliation' => 'Universitas Hasanuddin',
            'participation_type' => 'presenter',
            'registrant_category' => 'general',
        ]);
    }

    private function user(string $role): User
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
}
