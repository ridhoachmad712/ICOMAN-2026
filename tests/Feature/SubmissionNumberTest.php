<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Edition;
use App\Models\Submission;
use Tests\TestCase;

/**
 * Kode submission dulu memakai ULID (mis. ICOMAN2026-01M2AVZWQFBERZ45D5PM0CXA0C):
 * aman dari tabrakan tapi mustahil dibacakan lewat telepon. Sekarang nomor urut
 * pendek per-edition.
 */
class SubmissionNumberTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
    }

    private function submit(string $email, string $title = 'Judul'): Submission
    {
        $author = Author::create([
            'name' => 'Penulis', 'email' => $email, 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'general',
        ]);

        return Submission::create([
            'edition_id' => $this->edition->id,
            'author_id' => $author->id,
            'title' => $title,
            'abstract' => 'Isi abstrak.',
            'status' => 'draft',
        ]);
    }

    public function test_the_first_submission_gets_a_short_sequential_code(): void
    {
        $submission = $this->submit('satu@example.test');

        $this->assertSame('ICOMAN2026-001', $submission->submission_number);
        $this->assertLessThanOrEqual(20, strlen($submission->submission_number));
    }

    public function test_codes_run_in_sequence(): void
    {
        $numbers = collect(['a', 'b', 'c'])
            ->map(fn (string $slug) => $this->submit($slug.'@example.test')->submission_number);

        $this->assertSame(['ICOMAN2026-001', 'ICOMAN2026-002', 'ICOMAN2026-003'], $numbers->all());
    }

    public function test_the_prefix_follows_the_edition_name(): void
    {
        $other = Edition::create(['name' => 'ICOMAN 2027', 'is_active' => false]);
        $author = Author::create([
            'name' => 'Penulis', 'email' => 'edisi@example.test', 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'general',
        ]);

        $submission = Submission::create([
            'edition_id' => $other->id, 'author_id' => $author->id,
            'title' => 'Judul', 'abstract' => 'Isi.', 'status' => 'draft',
        ]);

        // Penomoran terpisah per edisi: edisi baru mulai lagi dari 001.
        $this->assertSame('ICOMAN2027-001', $submission->submission_number);
    }

    /** Kode ULID lama tetap utuh dan tidak mengacaukan penomoran baru. */
    public function test_legacy_ulid_codes_are_ignored_when_numbering(): void
    {
        $legacy = $this->submit('lama@example.test');
        $legacy->forceFill(['submission_number' => 'ICOMAN2026-01M2AVZWQFBERZ45D5PM0CXA0C'])->save();

        $fresh = $this->submit('baru@example.test');

        $this->assertSame('ICOMAN2026-001', $fresh->submission_number);
        $this->assertSame('ICOMAN2026-01M2AVZWQFBERZ45D5PM0CXA0C', $legacy->fresh()->submission_number);
    }

    /** Nomor yang sudah terpakai tidak boleh dipakai ulang setelah penghapusan. */
    public function test_a_taken_code_is_never_reissued(): void
    {
        $this->submit('satu@example.test');
        $second = $this->submit('dua@example.test');
        $this->assertSame('ICOMAN2026-002', $second->submission_number);

        // Baris kedua hilang; kiriman berikutnya mengisi celahnya, bukan menabrak.
        $second->delete();
        $third = $this->submit('tiga@example.test');

        $this->assertSame('ICOMAN2026-002', $third->submission_number);
        $this->assertDatabaseCount('submissions', 2);
    }

    /** Kode eksplisit dari pemanggil tidak boleh ditimpa. */
    public function test_an_explicit_code_is_respected(): void
    {
        $author = Author::create([
            'name' => 'Penulis', 'email' => 'manual@example.test', 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'general',
        ]);

        $submission = Submission::create([
            'edition_id' => $this->edition->id, 'author_id' => $author->id,
            'submission_number' => 'KHUSUS-999',
            'title' => 'Judul', 'abstract' => 'Isi.', 'status' => 'draft',
        ]);

        $this->assertSame('KHUSUS-999', $submission->submission_number);
    }
}
