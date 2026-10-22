<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Edition;
use App\Models\ImportantDate;
use Tests\TestCase;

/**
 * Pendaftaran ditutup per jalur mengikuti Tanggal Penting: presenter saat
 * tenggat abstrak, peserta seminar dan co-host saat tenggat pembayaran.
 */
class RegistrationClosingTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
    }

    public function test_presenter_registration_closes_after_the_abstract_deadline(): void
    {
        $this->deadline('abstract', now()->subDay());
        $this->deadline('payment', now()->addWeek());

        $this->get(route('author.register.terms', ['role' => 'presenter']))
            ->assertRedirect(route('author.register'))
            ->assertSessionHas('error', 'Registration as a presenter has closed.');

        // Formulir yang dibuka sebelum tenggat lalu dikirim sesudahnya juga ditolak.
        $this->withSession(['author_terms_ok' => 'presenter'])
            ->post(route('author.register'), $this->form('presenter', 'telat@example.test'))
            ->assertRedirect(route('author.register'));
        $this->assertDatabaseMissing('authors', ['email' => 'telat@example.test']);

        // Peserta seminar masih bisa mendaftar.
        $this->get(route('author.register.terms', ['role' => 'non_presenter']))->assertOk();
        $this->withSession(['author_terms_ok' => 'non_presenter'])
            ->post(route('author.register'), $this->form('non_presenter', 'peserta@example.test'))
            ->assertRedirect(route('author.registration.checkout'));
        $this->assertDatabaseHas('authors', ['email' => 'peserta@example.test']);
    }

    public function test_attendee_and_cohost_registration_close_after_the_payment_deadline(): void
    {
        $this->deadline('abstract', now()->subWeeks(2));
        $this->deadline('payment', now()->subDay());

        foreach (['non_presenter', 'cohost'] as $role) {
            $this->get(route('author.register.terms', ['role' => $role]))->assertRedirect(route('author.register'));
            $this->get(route('author.register.start', ['role' => $role]))->assertRedirect(route('author.register'));
        }

        $this->withSession(['author_terms_ok' => 'non_presenter'])
            ->post(route('author.register'), $this->form('non_presenter', 'telat@example.test'))
            ->assertRedirect(route('author.register'));

        $this->withSession(['author_terms_ok' => 'cohost'])
            ->post(route('author.register.cohost'), [])
            ->assertRedirect(route('author.register'))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Author::count());
    }

    public function test_the_choice_page_marks_closed_paths(): void
    {
        $this->deadline('abstract', now()->subDay());
        $this->deadline('payment', now()->addWeek());

        $this->get(route('author.register'))
            ->assertOk()
            ->assertSee('Registration closed')
            ->assertDontSee(route('author.register.terms', ['role' => 'presenter']), false)
            ->assertSee(route('author.register.terms', ['role' => 'non_presenter']), false);
    }

    public function test_paths_stay_open_while_no_deadline_is_set(): void
    {
        foreach (['presenter', 'non_presenter', 'cohost'] as $role) {
            $this->get(route('author.register.terms', ['role' => $role]))->assertOk();
        }
    }

    private function deadline(string $kind, \DateTimeInterface $at): void
    {
        ImportantDate::create([
            'edition_id' => $this->edition->id,
            'label' => ['id' => 'Tenggat '.$kind, 'en' => ucfirst($kind).' deadline'],
            'kind' => $kind,
            'date' => $at,
            'closes_at' => $at,
            'order' => 1,
        ]);
    }

    /** @return array<string, string> */
    private function form(string $role, string $email): array
    {
        return [
            'name' => 'Pendaftar',
            'email' => $email,
            'participation_type' => $role,
            'registrant_category' => 'general',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ];
    }
}
