<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Edition;
use App\Models\ReviewAssignment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Unduhan full paper dari panel admin dulu selalu berakhir 500: header
 * dipasang dengan ->header() pada BinaryFileResponse, yang tidak punya method
 * itu. Tes ini mengunduh sungguhan, bukan hanya memeriksa tombolnya ada.
 */
class FullPaperDownloadTest extends TestCase
{
    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('papers');
        $edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $author = Author::create([
            'name' => 'Rina', 'email' => 'rina-fp@example.test', 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'general',
        ]);
        $this->submission = Submission::create([
            'edition_id' => $edition->id, 'author_id' => $author->id, 'title' => 'Paper',
            'abstract' => 'Abstract', 'keywords' => ['k'], 'status' => 'accepted',
        ]);
        // Full paper wajib .docx; berkas Word sungguhan dibuat dari generator abstrak.
        $docx = app(\App\Services\AbstractWordDocument::class)->create($this->submission);
        $this->submission->addMedia(UploadedFile::fake()->createWithContent('Naskah Final – Revisi.docx', file_get_contents($docx)))
            ->toMediaCollection('camera_ready');
    }

    public function test_committee_roles_can_download_the_full_paper(): void
    {
        foreach (['superadmin', 'admin_registrasi'] as $role) {
            $response = $this->actingAs($this->user($role), 'web')
                ->get(route('admin.submissions.full-paper.download', $this->submission))
                ->assertOk()
                ->assertDownload();

            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
            // Berkas .docx adalah arsip zip: diawali tanda "PK".
            $this->assertStringStartsWith('PK', file_get_contents($response->baseResponse->getFile()->getPathname()));
        }
    }

    public function test_the_assigned_reviewer_can_download_but_others_cannot(): void
    {
        $reviewer = $this->user('reviewer');
        ReviewAssignment::create([
            'submission_id' => $this->submission->id, 'reviewer_id' => $reviewer->id,
            'phase' => 'extended_abstract', 'assigned_at' => now(), 'status' => 'completed',
        ]);

        $this->actingAs($reviewer, 'web')
            ->get(route('admin.submissions.full-paper.download', $this->submission))
            ->assertOk();

        $this->actingAs($this->user('reviewer'), 'web')
            ->get(route('admin.submissions.full-paper.download', $this->submission))
            ->assertForbidden();
    }

    public function test_the_author_can_download_their_own_full_paper(): void
    {
        $this->actingAs($this->submission->author, 'author')
            ->get(route('author.submissions.full-paper.download', $this->submission))
            ->assertOk()
            ->assertDownload();
    }

    public function test_a_file_missing_from_disk_answers_404_instead_of_500(): void
    {
        unlink($this->submission->fullPaperMedia()->getPath());

        $this->actingAs($this->user('superadmin'), 'web')
            ->get(route('admin.submissions.full-paper.download', $this->submission))
            ->assertNotFound();
    }

    private function user(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::create(['name' => ucfirst($role), 'email' => $role.'-'.uniqid().'@example.test', 'password' => 'secret-password']);
        $user->assignRole($role);

        return $user;
    }
}
