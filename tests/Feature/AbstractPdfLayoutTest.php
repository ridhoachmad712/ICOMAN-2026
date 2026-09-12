<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Edition;
use App\Models\Submission;
use App\Models\SubmissionAuthor;
use App\Settings\SiteSettings;
use Tests\TestCase;

/**
 * Lembar abstract yang dilihat reviewer dan admin. Isinya dulu dirender sebagai
 * satu blok <br>, sehingga aturan paragraf (rata kanan-kiri, indent, jarak)
 * tidak pernah berlaku, dan nama konferensi ditulis tetap di templatenya.
 */
class AbstractPdfLayoutTest extends TestCase
{
    private function submission(string $abstract): Submission
    {
        $edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $author = Author::create([
            'name' => 'Penulis', 'email' => 'pdf@example.test', 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'general',
        ]);

        $submission = Submission::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'title' => 'Green Supply Chain Practices',
            'abstract' => $abstract,
            'keywords' => ['supply chain', 'SMEs'],
            'status' => 'extended_abstract_submitted',
        ]);

        SubmissionAuthor::create([
            'submission_id' => $submission->id,
            'name' => 'Penulis Utama',
            'email' => 'utama@example.test',
            'affiliation' => 'Universitas Negeri Makassar',
            'is_corresponding' => true,
            'order' => 1,
        ]);

        return $submission->fresh(['authors', 'edition', 'topic']);
    }

    private function render(Submission $submission): string
    {
        return view('pdf.extended-abstract', ['submission' => $submission])->render();
    }

    public function test_paragraphs_become_real_paragraphs(): void
    {
        $submission = $this->submission("Paragraf pertama.\n\nParagraf kedua.");

        $html = $this->render($submission);

        $this->assertStringContainsString('<p>Paragraf pertama.</p>', $html);
        $this->assertStringContainsString('<p>Paragraf kedua.</p>', $html);
        // Satu blok <br> membuat seluruh abstrak tidak bisa dirata-kanan-kiri.
        $this->assertStringNotContainsString('Paragraf pertama.<br />'."\n".'<br />', $html);
    }

    public function test_a_single_line_break_stays_inside_one_paragraph(): void
    {
        $html = $this->render($this->submission("Baris satu.\nBaris dua."));

        $this->assertMatchesRegularExpression('/<p>Baris satu\.<br\s*\/?>\s*Baris dua\.<\/p>/', $html);
    }

    public function test_the_abstract_body_is_justified(): void
    {
        $html = $this->render($this->submission('Isi abstrak.'));

        $this->assertMatchesRegularExpression('/\.abstract-content p \{[^}]*text-align: justify/s', $html);
    }

    public function test_the_conference_name_comes_from_data_not_the_template(): void
    {
        $submission = $this->submission('Isi abstrak.');
        $submission->edition->forceFill(['name' => 'ICOMAN 2030'])->save();

        $html = $this->render($submission->fresh(['authors', 'edition', 'topic']));

        $this->assertStringContainsString('ICOMAN 2030', $html);
        $this->assertStringNotContainsString('International Conference on Management', $html);
    }

    public function test_the_meta_line_carries_code_and_submission_date(): void
    {
        $submission = $this->submission('Isi abstrak.');

        $html = $this->render($submission);

        $this->assertStringContainsString('Paper ID: '.$submission->submission_number, $html);
        $this->assertStringContainsString('Submitted: '.$submission->submitted_at->format('d M Y'), $html);
    }

    /** Keywords menempel di blok abstract, bukan melayang di bawahnya. */
    public function test_keywords_sit_inside_the_abstract_block(): void
    {
        $html = $this->render($this->submission('Isi abstrak.'));

        $abstractBlock = substr($html, strpos($html, 'class="abstract-block"'));
        $this->assertStringContainsString('supply chain; SMEs', substr($abstractBlock, 0, strpos($abstractBlock, '</section>')));
    }

    /** Kata "Abstract" dulu muncul dua kali berturut-turut (badge + judul bagian). */
    public function test_the_abstract_label_is_not_repeated(): void
    {
        $html = $this->render($this->submission('Isi abstrak.'));

        $this->assertStringNotContainsString('class="paper-type"', $html);
    }

    public function test_the_pdf_endpoint_still_returns_a_pdf(): void
    {
        $submission = $this->submission('Isi abstrak.');
        $this->actingAs($submission->author, 'author');

        $response = $this->get(route('author.submissions.extended-abstract.preview', $submission))->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    /** Tanpa logo tersimpan, kop tetap menampilkan nama konferensi. */
    public function test_the_masthead_falls_back_to_the_conference_name(): void
    {
        app(SiteSettings::class)->fill(['logo' => null])->save();

        $html = $this->render($this->submission('Isi abstrak.'));

        $this->assertStringContainsString('class="logo-fallback"', $html);
        $this->assertStringContainsString('ICOMAN 2026', $html);
    }
}
