<?php

namespace Tests\Feature;

use App\Filament\Author\Pages\AuthorDashboard;
use App\Filament\Author\Pages\AuthorProfile;
use App\Filament\Author\Resources\Papers\PaperResource;
use App\Filament\Author\Resources\Registrations\RegistrationResource;
use App\Models\Author;
use App\Models\Edition;
use App\Models\RegistrationFee;
use App\Models\Submission;
use App\Models\Topic;
use App\Services\RegistrationProvisioner;
use Tests\TestCase;

/**
 * Membuka SETIAP halaman portal author sebagai pengguna sungguhan.
 *
 * Dibuat setelah /author/papers/create membalas 500: halaman itu tidak pernah
 * dibuka tes mana pun, sehingga hilangnya halaman index resource (yang masih
 * dibutuhkan Filament untuk tombol Cancel) lolos sampai produksi.
 */
class AuthorPortalPagesRenderTest extends TestCase
{
    public function test_a_brand_new_presenter_can_open_the_create_paper_page(): void
    {
        $edition = $this->edition();
        $this->topic($edition);
        $author = $this->presenter();

        // Persis langkah setelah pendaftaran: redirect ke /author/papers/create.
        $this->withoutExceptionHandling();
        $this->actingAs($author, 'author')
            ->get(PaperResource::getUrl('create', panel: 'author'))
            ->assertOk();
    }

    public function test_every_presenter_page_renders(): void
    {
        $edition = $this->edition();
        $this->topic($edition);
        $author = $this->presenter();
        $submission = Submission::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'title' => 'A paper',
            'abstract' => str_repeat('word ', 200),
            'status' => 'extended_abstract_draft',
        ]);

        $this->withoutExceptionHandling();

        foreach ([
            'dashboard' => AuthorDashboard::getUrl(panel: 'author'),
            'profile' => AuthorProfile::getUrl(panel: 'author'),
            'paper view' => PaperResource::getUrl('view', ['record' => $submission], panel: 'author'),
            'abstract editor' => PaperResource::getUrl('extended-abstract', ['record' => $submission], panel: 'author'),
        ] as $label => $url) {
            $this->actingAs($author, 'author')->get($url)->assertOk("Halaman [{$label}] gagal dirender.");
        }
    }

    public function test_every_participant_page_renders_including_the_auto_invoice(): void
    {
        $edition = $this->edition();
        $author = Author::create([
            'name' => 'Attendee',
            'email' => 'attendee@example.test',
            'password' => 'secret-password',
            'participation_type' => 'participant',
            'registrant_category' => 'general',
        ]);
        RegistrationFee::create([
            'edition_id' => $edition->id,
            'category' => ['en' => 'Seminar attendee'],
            'audience' => 'participant',
            'registrant_category' => 'general',
            'price_regular' => 50000,
            'currency' => 'IDR',
        ]);

        $registration = app(RegistrationProvisioner::class)->ensureFor($author);
        $this->assertNotNull($registration);

        $this->withoutExceptionHandling();

        foreach ([
            'dashboard' => AuthorDashboard::getUrl(panel: 'author'),
            'profile' => AuthorProfile::getUrl(panel: 'author'),
            'invoice' => RegistrationResource::getUrl('view', ['record' => $registration], panel: 'author'),
        ] as $label => $url) {
            $this->actingAs($author, 'author')->get($url)->assertOk("Halaman [{$label}] gagal dirender.");
        }

        // Checkout otomatis harus mengarah ke invoice, bukan error.
        $this->actingAs($author, 'author')
            ->get(route('author.registration.checkout'))
            ->assertRedirect(RegistrationResource::getUrl('view', ['record' => $registration], panel: 'author'));
    }

    /** Resource tanpa halaman index tetap harus bisa memberi URL "index" ke Filament. */
    public function test_resources_without_an_index_page_still_resolve_an_index_url(): void
    {
        $dashboard = AuthorDashboard::getUrl(panel: 'author');

        $this->assertSame($dashboard, PaperResource::getIndexUrl(panel: 'author'));
        $this->assertSame($dashboard, RegistrationResource::getIndexUrl(panel: 'author'));
    }

    private function edition(): Edition
    {
        return Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
    }

    private function topic(Edition $edition): Topic
    {
        return Topic::create(['edition_id' => $edition->id, 'title' => ['en' => 'Management'], 'order' => 1]);
    }

    private function presenter(): Author
    {
        return Author::create([
            'name' => 'New Presenter',
            'email' => 'presenter@example.test',
            'password' => 'secret-password',
            'participation_type' => 'presenter',
            'registrant_category' => 'general',
        ]);
    }
}
