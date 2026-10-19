<?php

namespace Tests\Feature;

use App\Filament\Author\Pages\AuthorDashboard;
use App\Filament\Author\Resources\Registrations\RegistrationResource;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Settings\SiteSettings;
use Tests\TestCase;

/**
 * Tautan grup WhatsApp peserta hanya untuk yang sudah lunas, baik presenter
 * maupun peserta seminar, dan dikelola panitia lewat Pengaturan.
 */
class WhatsappGroupLinkTest extends TestCase
{
    private const LINK = 'https://chat.whatsapp.com/Jhc3ulqkUxDEm6u9pknk8k';

    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
    }

    public function test_the_default_link_is_the_committee_group(): void
    {
        $this->assertSame(self::LINK, app(SiteSettings::class)->whatsapp_group_url);
    }

    public function test_a_paid_presenter_sees_the_group_link(): void
    {
        $this->assertLinkVisibility('presenter', 'paid', visible: true);
    }

    public function test_a_paid_participant_sees_the_group_link(): void
    {
        $this->assertLinkVisibility('participant', 'paid', visible: true);
    }

    public function test_an_unpaid_participant_does_not_see_the_group_link(): void
    {
        $this->assertLinkVisibility('participant', 'pending', visible: false);
    }

    public function test_a_payment_awaiting_verification_does_not_show_the_group_link(): void
    {
        $this->assertLinkVisibility('presenter', 'pending_verification', visible: false);
    }

    public function test_an_empty_setting_hides_the_card(): void
    {
        $settings = app(SiteSettings::class);
        $settings->whatsapp_group_url = null;
        $settings->save();

        [$author] = $this->registration('participant', 'paid');

        $this->actingAs($author, 'author')
            ->get(AuthorDashboard::getUrl(panel: 'author'))
            ->assertOk()
            ->assertDontSee('chat.whatsapp.com', false);
    }

    private function assertLinkVisibility(string $type, string $status, bool $visible): void
    {
        [$author, $registration] = $this->registration($type, $status);
        $this->actingAs($author, 'author');

        foreach ([
            AuthorDashboard::getUrl(panel: 'author'),
            RegistrationResource::getUrl('view', ['record' => $registration], panel: 'author'),
        ] as $url) {
            $response = $this->get($url)->assertOk();
            $visible ? $response->assertSee(self::LINK, false) : $response->assertDontSee(self::LINK, false);
        }
    }

    /** @return array{0: Author, 1: Registration} */
    private function registration(string $type, string $status): array
    {
        $author = Author::create([
            'name' => 'Peserta',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
            'participation_type' => $type,
            'registrant_category' => 'general',
        ]);

        $fee = RegistrationFee::create([
            'edition_id' => $this->edition->id,
            'category' => ['en' => ucfirst($type)],
            'audience' => $type,
            'registrant_category' => 'general',
            'price_regular' => 50_000,
            'currency' => 'IDR',
        ]);

        $registration = Registration::create([
            'edition_id' => $this->edition->id,
            'author_id' => $author->id,
            'registration_fee_id' => $fee->id,
            'amount' => 50_000,
            'currency' => 'IDR',
            'payment_method' => 'manual',
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : null,
        ]);

        return [$author, $registration];
    }
}
