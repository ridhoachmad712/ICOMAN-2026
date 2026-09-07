<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Edition;
use App\Models\Submission;
use Tests\TestCase;

class AbstractLimitsAndCountryTest extends TestCase
{
    /** Kriteria abstract kini 200-250 kata. */
    public function test_abstract_must_be_between_200_and_250_words(): void
    {
        $this->assertSame(200, Submission::ABSTRACT_MIN_WORDS);
        $this->assertSame(250, Submission::ABSTRACT_MAX_WORDS);

        $edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $author = $this->author('presenter', 'a@example.test');
        $submission = Submission::create([
            'edition_id' => $edition->id, 'author_id' => $author->id, 'title' => 'Paper',
            'abstract' => '', 'status' => 'extended_abstract_draft',
        ]);

        $words = fn (int $n) => implode(' ', array_fill(0, $n, 'management'));

        // Terlalu pendek (di bawah batas lama 150 pun kini ditolak di 199).
        $this->actingAs($author, 'author')
            ->post(route('author.submissions.extended-abstract', $submission), ['abstract' => $words(199)])
            ->assertSessionHasErrors('abstract');

        // Terlalu panjang (250 adalah batas atas baru; 251 ditolak).
        $this->actingAs($author, 'author')
            ->post(route('author.submissions.extended-abstract', $submission), ['abstract' => $words(251)])
            ->assertSessionHasErrors('abstract');

        // Dalam rentang → diterima.
        $this->actingAs($author, 'author')
            ->post(route('author.submissions.extended-abstract', $submission), ['abstract' => $words(220)])
            ->assertSessionHasNoErrors();

        $this->assertSame('extended_abstract_submitted', $submission->refresh()->status);
    }

    /** Negara dipilih dari daftar, dengan Indonesia di urutan teratas. */
    public function test_country_list_puts_indonesia_first_then_alphabetical(): void
    {
        $options = countryOptions();
        $keys = array_keys($options);

        $this->assertSame('ID', $keys[0]);
        $this->assertSame('Indonesia', $options['ID']);

        $rest = array_slice(array_values($options), 1);
        $sorted = $rest;
        sort($sorted, SORT_NATURAL | SORT_FLAG_CASE);
        $this->assertSame($sorted, $rest, 'Negara selain Indonesia harus urut abjad.');
    }

    public function test_registration_form_renders_a_country_dropdown(): void
    {
        Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);

        $this->withSession(['author_terms_ok' => 'presenter'])
            ->get(route('author.register.start', ['role' => 'presenter']))
            ->assertOk()
            ->assertSee('<select id="field-country"', false)
            ->assertSee('<option value="ID"', false);
    }

    public function test_registration_rejects_a_country_outside_the_list(): void
    {
        Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);

        $payload = [
            'name' => 'Someone', 'email' => 'someone@example.test',
            'participation_type' => 'presenter', 'registrant_category' => 'general',
            'password' => 'StrongPassword123!', 'password_confirmation' => 'StrongPassword123!',
        ];

        $this->withSession(['author_terms_ok' => 'presenter'])
            ->post(route('author.register'), $payload + ['country' => 'Negeri Antah Berantah'])
            ->assertSessionHasErrors('country');

        $this->withSession(['author_terms_ok' => 'presenter'])
            ->post(route('author.register'), $payload + ['country' => 'ID'])
            ->assertSessionHasNoErrors();

        $this->assertSame('ID', Author::firstOrFail()->country);
    }

    private function author(string $type, string $email): Author
    {
        return Author::create([
            'name' => 'P', 'email' => $email, 'password' => 'secret-password',
            'participation_type' => $type, 'registrant_category' => 'general',
        ]);
    }
}
