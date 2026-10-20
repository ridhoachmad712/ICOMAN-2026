<?php

namespace Tests\Feature;

use App\Filament\Resources\Submissions\Pages\ListSubmissions;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Submission;
use App\Models\Topic;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

/**
 * Unduhan panitia dari halaman Submissions: daftar paper dalam Excel dan
 * abstrak Accepted dalam satu berkas Word.
 */
class SubmissionDownloadsTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);

        Role::findOrCreate('admin_registrasi', 'web');
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-downloads@example.test', 'password' => 'secret-password']);
        $admin->assignRole('admin_registrasi');
        $this->actingAs($admin, 'web');
    }

    public function test_the_paper_list_has_name_authors_title_affiliation_and_sub_theme(): void
    {
        $topic = $this->topic(1, 'Sub-tema 1: Ketahanan SDM');
        $paper = $this->paper('accepted', $topic, 'Rina Saputri', 'Universitas Hasanuddin', 'Dampak AI, Ketahanan, dan Bisnis');
        $paper->authors()->create(['name' => 'Rina Saputri', 'email' => 'rina@example.test', 'affiliation' => 'Universitas Hasanuddin', 'is_corresponding' => true, 'order' => 1]);
        $paper->authors()->create(['name' => 'Andi Wijaya', 'email' => 'andi@example.test', 'affiliation' => 'UNM', 'order' => 2]);

        $rows = $this->xlsxRows($this->download('downloadPaperList', 'accepted'));

        $this->assertSame(['No', 'Kode', 'Nama', 'Jenis kepesertaan', 'Semua penulis', 'Judul Paper', 'Afiliasi', 'Sub-tema', 'Status', 'Full paper', 'Tanggal full paper'], $rows[0]);
        $this->assertSame('Rina Saputri', $rows[1][2]);
        $this->assertSame('Dosen / Umum', $rows[1][3]);
        $this->assertSame('Rina Saputri (Universitas Hasanuddin); Andi Wijaya (UNM)', $rows[1][4]);
        $this->assertSame('Dampak AI, Ketahanan, dan Bisnis', $rows[1][5]);
        $this->assertSame('Universitas Hasanuddin', $rows[1][6]);
        $this->assertSame('Sub-tema 1: Ketahanan SDM', $rows[1][7]);
    }

    public function test_the_paper_list_shows_each_participation_type(): void
    {
        $topic = $this->topic(1, 'Sub-tema 1');
        foreach (['student_s1' => 'Mahasiswa', 'general' => 'Dosen', 'international' => 'Asing'] as $category => $name) {
            $this->paper('accepted', $topic, $name, 'Kampus', 'Paper '.$name)
                ->author->update(['registrant_category' => $category]);
        }

        $types = collect(array_slice($this->xlsxRows($this->download('downloadPaperList', 'accepted')), 1))
            ->mapWithKeys(fn (array $row) => [$row[2] => $row[3]])
            ->all();

        $this->assertSame('Mahasiswa S1', $types['Mahasiswa']);
        $this->assertSame('Dosen / Umum', $types['Dosen']);
        $this->assertSame('International', $types['Asing']);
    }

    public function test_accepted_abstracts_download_as_one_word_file_ordered_by_sub_theme(): void
    {
        $second = $this->topic(2, 'Sub-tema 2');
        $first = $this->topic(1, 'Sub-tema 1');
        $this->paper('accepted', $second, 'Budi', 'UNM', 'Paper sub-tema dua');
        $this->paper('accepted', $first, 'Rina', 'Unhas', 'Paper sub-tema satu');
        $this->paper('extended_abstract_under_review', $first, 'Citra', 'UI', 'Paper yang masih direview');

        $xml = $this->documentXml($this->download('downloadAcceptedAbstracts', 'all'));

        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringNotContainsString('Paper yang masih direview', $xml);
        $this->assertLessThan(strpos($xml, 'Paper sub-tema dua'), strpos($xml, 'Paper sub-tema satu'));
        $this->assertSame(1, substr_count($xml, 'w:type="page"'), 'Dua abstrak dipisah satu pemisah halaman.');
    }

    public function test_selected_papers_download_as_one_word_file(): void
    {
        $topic = $this->topic(1, 'Sub-tema 1');
        $picked = $this->paper('accepted', $topic, 'Rina', 'Unhas', 'Paper yang dipilih');
        $this->paper('accepted', $topic, 'Budi', 'UNM', 'Paper yang tidak dipilih');

        $component = Livewire::test(ListSubmissions::class)
            ->set('activeTab', 'accepted')
            ->selectTableRecords([$picked->getKey()])
            ->callAction(TestAction::make('downloadSelectedAbstracts')->table()->bulk())
            ->assertHasNoErrors();

        $path = tempnam(sys_get_temp_dir(), 'download-test-');
        file_put_contents($path, base64_decode($component->effects['download']['content']));
        $xml = $this->documentXml($path);

        $this->assertStringContainsString('Paper yang dipilih', $xml);
        $this->assertStringNotContainsString('Paper yang tidak dipilih', $xml);
    }

    public function test_no_accepted_abstract_means_no_file_and_a_warning(): void
    {
        $this->paper('extended_abstract_under_review', $this->topic(1, 'Sub-tema 1'), 'Citra', 'UI', 'Masih direview');

        Livewire::test(ListSubmissions::class)
            ->set('activeTab', 'all')
            ->callAction(TestAction::make('downloadAcceptedAbstracts')->table())
            ->assertNotified('Tidak ada abstrak Accepted di daftar ini')
            ->assertNoFileDownloaded();
    }

    public function test_the_full_paper_filter_separates_who_has_submitted(): void
    {
        Storage::fake('papers');
        $topic = $this->topic(1, 'Sub-tema 1');
        $done = $this->withFullPaper($this->paper('accepted', $topic, 'Rina', 'Unhas', 'Sudah kumpul'));
        $missing = $this->paper('accepted', $topic, 'Budi', 'UNM', 'Belum kumpul');

        Livewire::test(ListSubmissions::class)
            ->set('activeTab', 'accepted')
            ->filterTable('full_paper', true)
            ->assertCanSeeTableRecords([$done])
            ->assertCanNotSeeTableRecords([$missing])
            ->filterTable('full_paper', false)
            ->assertCanSeeTableRecords([$missing])
            ->assertCanNotSeeTableRecords([$done]);

        $rows = $this->xlsxRows($this->download('downloadPaperList', 'accepted'));
        $fullPaper = collect(array_slice($rows, 1))->mapWithKeys(fn (array $row) => [$row[2] => $row[9]])->all();
        $this->assertSame('Sudah', $fullPaper['Rina']);
        $this->assertSame('Belum', $fullPaper['Budi']);
    }

    public function test_the_full_paper_tabs_list_only_authors_who_uploaded(): void
    {
        Storage::fake('papers');
        $topic = $this->topic(1, 'Sub-tema 1');
        $done = $this->withFullPaper($this->paper('accepted', $topic, 'Rina', 'Unhas', 'Sudah kumpul'));
        $missing = $this->paper('accepted', $topic, 'Budi', 'UNM', 'Belum kumpul');

        Livewire::test(ListSubmissions::class)
            ->set('activeTab', 'full_paper')
            ->assertCanSeeTableRecords([$done])
            ->assertCanNotSeeTableRecords([$missing]);

        Livewire::test(\App\Filament\Resources\Authors\Pages\ListAuthors::class)
            ->set('activeTab', 'full_paper')
            ->assertCanSeeTableRecords([$done->author])
            ->assertCanNotSeeTableRecords([$missing->author]);
    }

    public function test_full_papers_download_as_one_zip_named_by_code_and_title(): void
    {
        Storage::fake('papers');
        $topic = $this->topic(1, 'Sub-tema 1');
        $paper = $this->withFullPaper($this->paper('accepted', $topic, 'Rina', 'Unhas', 'Dampak AI: Studi Kasus'));
        $this->paper('accepted', $topic, 'Budi', 'UNM', 'Belum kumpul');

        $path = $this->download('downloadFullPapers', 'accepted');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $names = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $names[] = $zip->getNameIndex($index);
        }
        $content = $zip->getFromIndex(0);
        $zip->close();
        unlink($path);

        // Titik dua tidak sah di nama berkas Windows, jadi diganti spasi.
        $this->assertSame([$paper->submission_number.' - Dampak AI  Studi Kasus.pdf'], $names);
        $this->assertSame('%PDF-1.4 full paper', $content);
    }

    public function test_no_full_paper_means_no_zip_and_a_warning(): void
    {
        $this->paper('accepted', $this->topic(1, 'Sub-tema 1'), 'Budi', 'UNM', 'Belum kumpul');

        Livewire::test(ListSubmissions::class)
            ->set('activeTab', 'accepted')
            ->callAction(TestAction::make('downloadFullPapers')->table())
            ->assertNotified('Belum ada full paper di daftar ini')
            ->assertNoFileDownloaded();
    }

    public function test_the_detail_page_links_the_full_paper(): void
    {
        Storage::fake('papers');
        $paper = $this->withFullPaper($this->paper('accepted', $this->topic(1, 'Sub-tema 1'), 'Rina', 'Unhas', 'Paper lengkap'));

        $this->get(SubmissionResource::getUrl('edit', ['record' => $paper]))
            ->assertOk()
            ->assertSee(route('admin.submissions.full-paper.download', $paper), false)
            ->assertSee('naskah-final.pdf');
    }

    private function withFullPaper(Submission $submission): Submission
    {
        $submission->addMedia(UploadedFile::fake()->createWithContent('naskah-final.pdf', '%PDF-1.4 full paper'))
            ->toMediaCollection('camera_ready');
        $submission->forceFill(['full_paper_submitted_at' => now()])->save();

        return $submission->refresh();
    }

    private function download(string $action, string $tab): string
    {
        $component = Livewire::test(ListSubmissions::class)
            ->set('activeTab', $tab)
            ->callAction(TestAction::make($action)->table())
            ->assertHasNoErrors();

        $download = $component->effects['download'] ?? null;
        $this->assertNotNull($download, 'Tidak ada berkas yang diunduh.');

        $path = tempnam(sys_get_temp_dir(), 'download-test-');
        file_put_contents($path, base64_decode($download['content']));

        return $path;
    }

    /** @return list<list<mixed>> */
    private function xlsxRows(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            break;
        }
        $reader->close();
        unlink($path);

        return $rows;
    }

    private function documentXml(string $path): string
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($path);

        return $xml;
    }

    private function topic(int $order, string $title): Topic
    {
        return Topic::create(['edition_id' => $this->edition->id, 'order' => $order, 'title' => ['en' => $title, 'id' => $title]]);
    }

    private function paper(string $status, Topic $topic, string $name, string $affiliation, string $title): Submission
    {
        $author = Author::create([
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
            'affiliation' => $affiliation,
            'participation_type' => 'presenter',
            'registrant_category' => 'general',
        ]);

        return Submission::create([
            'edition_id' => $this->edition->id,
            'author_id' => $author->id,
            'topic_id' => $topic->id,
            'title' => $title,
            'abstract' => implode(' ', array_fill(0, 220, 'management')),
            'keywords' => ['management'],
            'status' => $status,
        ]);
    }
}
