<?php

namespace Tests\Feature;

use App\Filament\Author\Pages\AuthorDashboard;
use App\Filament\Author\Resources\Papers\Pages\CreatePaper;
use App\Filament\Author\Resources\Papers\PaperResource;
use App\Models\Author;
use App\Models\Edition;
use App\Models\ImportantDate;
use App\Models\Submission;
use App\Models\Topic;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Continue Writing" di halaman Start Abstract tidak melakukan apa-apa.
 *
 * Pemeriksaan sebelum simpan melampirkan pesannya ke `data.title`, yang berada
 * di langkah pertama wizard — sementara tombolnya ditekan dari langkah kedua.
 * Pesannya ada, tapi tidak pernah terlihat penulis.
 */
class StartAbstractBlockedTest extends TestCase
{
    private Edition $edition;

    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('author'));
        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $this->topic = Topic::create([
            'edition_id' => $this->edition->id,
            'title' => ['id' => 'Topik', 'en' => 'Topic'],
            'order' => 1,
        ]);
    }

    private function presenter(string $email = 'penulis@example.test'): Author
    {
        $author = Author::create([
            'name' => 'Penulis', 'email' => $email, 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'general',
        ]);
        $this->actingAs($author, 'author');

        return $author;
    }

    private function fillWizard(): Testable
    {
        return Livewire::test(CreatePaper::class)->fillForm([
            'title' => 'Judul Paper',
            'topic_id' => $this->topic->id,
            'keywords' => ['satu'],
            'authors' => [
                ['name' => 'Penulis', 'email' => 'penulis@example.test', 'affiliation' => 'UNM', 'is_corresponding' => true],
            ],
        ]);
    }

    public function test_a_presenter_can_start_an_abstract(): void
    {
        $this->presenter();

        $this->fillWizard()->call('create')->assertHasNoFormErrors();

        $this->assertSame(1, Submission::count());
    }

    /** Akun yang sudah punya paper tidak boleh terjebak di formulir tanpa kabar. */
    public function test_a_second_paper_sends_the_author_back_to_the_paper_they_have(): void
    {
        $author = $this->presenter();
        $existing = Submission::create([
            'edition_id' => $this->edition->id, 'author_id' => $author->id,
            'title' => 'Paper Pertama', 'abstract' => 'Isi.', 'status' => 'extended_abstract_draft',
        ]);

        Livewire::test(CreatePaper::class)
            ->assertNotified()
            ->assertRedirect(PaperResource::getUrl('view', ['record' => $existing], panel: 'author'));

        $this->assertSame(1, Submission::count());
    }

    /** Begitu pula bila tenggat abstrak sudah lewat: jangan biarkan mengisi sia-sia. */
    public function test_a_closed_deadline_turns_the_author_away_with_a_reason(): void
    {
        $this->presenter();
        ImportantDate::create([
            'edition_id' => $this->edition->id,
            'label' => ['id' => 'Batas', 'en' => 'Deadline'],
            'kind' => 'abstract',
            'date' => now()->subWeek(),
            'closes_at' => now()->subWeek(),
            'order' => 1,
        ]);

        Livewire::test(CreatePaper::class)
            ->assertNotified()
            ->assertRedirect(AuthorDashboard::getUrl(panel: 'author'));

        $this->assertSame(0, Submission::count());
    }

    /** Corresponding author ganda juga harus terbaca, bukan diam. */
    public function test_two_corresponding_authors_are_refused_with_a_visible_message(): void
    {
        $this->presenter();

        $component = Livewire::test(CreatePaper::class)
            ->fillForm([
                'title' => 'Judul Paper',
                'topic_id' => $this->topic->id,
                'keywords' => ['satu'],
                'authors' => [
                    ['name' => 'Penulis A', 'email' => 'a@example.test', 'is_corresponding' => true],
                    ['name' => 'Penulis B', 'email' => 'b@example.test', 'is_corresponding' => true],
                ],
            ])
            ->call('create');

        $this->assertSame(0, Submission::count());
        $component->assertNotified();
    }

    /**
     * Inti keluhannya: isian wajib yang kosong berada di langkah pertama,
     * sementara tombolnya ditekan dari langkah kedua — tanpa kabar apa pun.
     */
    public function test_a_missing_field_on_the_first_step_is_reported(): void
    {
        $this->presenter();

        $component = Livewire::test(CreatePaper::class)
            ->fillForm([
                'title' => 'Judul Paper',
                'topic_id' => $this->topic->id,
                'keywords' => [],
                'authors' => [
                    ['name' => 'Penulis', 'email' => 'penulis@example.test', 'is_corresponding' => true],
                ],
            ])
            ->call('create');

        $this->assertSame(0, Submission::count());
        $component->assertHasFormErrors(['keywords']);
        $component->assertNotified();
    }

    /**
     * Pesan validasi harus tetap berbahasa manusia. Penerjemah kustom untuk
     * Teks Website sempat membuang jalur bahasa bawaan framework, sehingga
     * seluruh pesan validasi tampil sebagai kunci mentah seperti
     * "validation.required".
     */
    public function test_validation_messages_are_still_translated(): void
    {
        app()->setLocale('en');

        $this->assertSame('The :attribute field is required.', trans('validation.required'));
        $this->assertNotSame('validation.email', trans('validation.email'));
    }
}
