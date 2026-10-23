<?php

namespace Tests\Feature;

use App\Filament\Author\Pages\AuthorDashboard;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\Submission;
use App\Services\AbstractWordDocument;
use App\Settings\SiteSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Link Zoom diisi panitia dan hanya tampil bagi yang berhak: peserta seminar
 * yang lunas, dan presenter yang lunas serta sudah mengumpulkan full paper.
 */
class ZoomAccessTest extends TestCase
{
    private const LINK = 'https://us02web.zoom.us/j/1234567890?pwd=icoman';

    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('papers');
        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);

        $settings = app(SiteSettings::class);
        $settings->zoom_url = self::LINK;
        $settings->zoom_meeting_id = '123 456 7890';
        $settings->zoom_passcode = 'icoman26';
        $settings->zoom_note = 'Ruang dibuka 08.30 WITA.';
        $settings->save();
    }

    public function test_a_paid_presenter_with_a_full_paper_sees_the_zoom_details(): void
    {
        $author = $this->author('presenter', paid: true, fullPaper: true);

        $this->dashboard($author)
            ->assertSee(self::LINK, false)
            ->assertSee('123 456 7890')
            ->assertSee('icoman26')
            ->assertSee('Ruang dibuka 08.30 WITA.');
    }

    public function test_a_paid_presenter_without_a_full_paper_only_sees_the_requirement(): void
    {
        $author = $this->author('presenter', paid: true, fullPaper: false);

        $this->dashboard($author)
            ->assertDontSee(self::LINK, false)
            ->assertDontSee('icoman26')
            ->assertSee('full paper');
    }

    public function test_a_paid_seminar_attendee_sees_the_zoom_link(): void
    {
        $this->dashboard($this->author('participant', paid: true))->assertSee(self::LINK, false);
    }

    public function test_unpaid_authors_do_not_see_the_zoom_link(): void
    {
        $this->dashboard($this->author('participant', paid: false))
            ->assertDontSee(self::LINK, false)
            ->assertDontSee('icoman26');
    }

    public function test_an_empty_link_hides_the_card(): void
    {
        $settings = app(SiteSettings::class);
        $settings->zoom_url = null;
        $settings->save();

        $this->dashboard($this->author('participant', paid: true))
            ->assertDontSee('icoman26')
            ->assertDontSee('Conference Zoom access');
    }

    private function dashboard(Author $author)
    {
        return $this->actingAs($author, 'author')
            ->get(AuthorDashboard::getUrl(panel: 'author'))
            ->assertOk();
    }

    private function author(string $type, bool $paid, bool $fullPaper = false): Author
    {
        $author = Author::create([
            'name' => 'Peserta', 'email' => fake()->unique()->safeEmail(), 'password' => 'secret-password',
            'participation_type' => $type, 'registrant_category' => 'general',
        ]);

        $submission = null;
        if ($type === 'presenter') {
            $submission = Submission::create([
                'edition_id' => $this->edition->id, 'author_id' => $author->id, 'title' => 'Paper',
                'abstract' => 'Abstract', 'keywords' => ['k'], 'status' => 'accepted', 'loa_issued_at' => now(),
            ]);

            if ($fullPaper) {
                $docx = app(AbstractWordDocument::class)->create($submission);
                $submission->addMedia(UploadedFile::fake()->createWithContent('naskah.docx', file_get_contents($docx)))
                    ->toMediaCollection('camera_ready');
            }
        }

        $fee = RegistrationFee::create([
            'edition_id' => $this->edition->id, 'category' => ['en' => ucfirst($type)],
            'audience' => $type, 'registrant_category' => 'general',
            'price_regular' => 50_000, 'currency' => 'IDR',
        ]);

        Registration::create([
            'edition_id' => $this->edition->id, 'author_id' => $author->id,
            'registration_fee_id' => $fee->id, 'submission_id' => $submission?->id,
            'amount' => 50_000, 'currency' => 'IDR', 'payment_method' => 'manual',
            'status' => $paid ? 'paid' : 'pending', 'paid_at' => $paid ? now() : null,
        ]);

        return $author;
    }
}
