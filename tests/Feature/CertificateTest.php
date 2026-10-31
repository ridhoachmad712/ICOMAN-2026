<?php

namespace Tests\Feature;

use App\Filament\Author\Pages\AuthorDashboard;
use App\Filament\Pages\Settings\ManageSiteSettings;
use App\Filament\Resources\Certificates\Pages\ListCertificates;
use App\Models\Author;
use App\Models\Certificate;
use App\Models\Edition;
use App\Models\PresentationRoom;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\RoomPresentation;
use App\Models\Submission;
use App\Models\User;
use App\Services\AbstractWordDocument;
use App\Services\CertificateIssuer;
use App\Services\CertificatePdf;
use App\Settings\SiteSettings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Sertifikat otomatis sesuai rancangan panitia: presenter satu per penulis
 * (lunas + full paper + tercatat hadir di room presentasi), peserta seminar
 * bila lunas,
 * diterbitkan lewat tombol admin, dan diverifikasi lewat QR.
 */
class CertificateTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('papers');
        $this->edition = Edition::create([
            'name' => 'ICOMAN 2026', 'is_active' => true,
            'theme' => ['en' => 'Navigating Business Resilience', 'id' => 'Menavigasi Ketahanan Bisnis'],
            'start_date' => '2026-11-07', 'end_date' => '2026-11-07',
        ]);
    }

    public function test_only_eligible_people_receive_certificates_and_reissuing_is_idempotent(): void
    {
        $paper = $this->presenter(paid: true, fullPaper: true, writers: [['Rina Saputri', 'Unhas'], ['Andi Wijaya', 'UNM']]);
        $this->presenter(paid: true, fullPaper: false, writers: [['Tanpa Full Paper', 'UI']]);
        $this->presenter(paid: false, fullPaper: true, writers: [['Belum Bayar', 'ITB']]);
        $this->presenter(paid: true, fullPaper: true, writers: [['Tidak Hadir', 'UGM']], attendance: 'absent');
        $this->presenter(paid: true, fullPaper: true, writers: [['Belum Diabsen', 'IPB']], attendance: null);
        $attendee = $this->attendee(paid: true, name: 'Citra Lestari');
        $this->attendee(paid: false, name: 'Peserta Belum Bayar');

        $issuer = app(CertificateIssuer::class);
        $this->assertSame(['presentation' => 2, 'participation' => 1], $issuer->pendingCounts($this->edition));
        $this->assertSame(3, $issuer->issuePending($this->edition));

        $presentations = Certificate::where('type', 'presentation')->orderBy('number')->get();
        $this->assertSame(['Rina Saputri', 'Andi Wijaya'], $presentations->pluck('recipient_name')->all());
        $this->assertSame(['Unhas', 'UNM'], $presentations->pluck('recipient_affiliation')->all());
        $this->assertSame([$paper->submission_number.'/P1', $paper->submission_number.'/P2'], $presentations->pluck('number')->all());
        // Semuanya diunduh lewat akun pengirim paper.
        $this->assertSame([$paper->author_id], $presentations->pluck('author_id')->unique()->values()->all());

        $participation = Certificate::where('type', 'participation')->sole();
        $this->assertSame('ICOMAN2026/A/0001', $participation->number);
        $this->assertSame($attendee->id, $participation->author_id);

        // Tombol yang sama ditekan lagi: tidak ada duplikat.
        $this->assertSame(0, $issuer->issuePending($this->edition));
        $this->assertSame(3, Certificate::count());
    }

    public function test_the_pdf_renders_with_a_qr_code_to_the_verification_page(): void
    {
        $this->attendee(paid: true, name: 'Citra Lestari');
        app(CertificateIssuer::class)->issuePending($this->edition);
        $certificate = Certificate::sole();

        $qr = app(CertificatePdf::class)->qrDataUri($certificate);
        $this->assertStringStartsWith('data:image/png;base64,', $qr);

        $html = view('pdf.certificate', [
            'certificate' => $certificate, 'settings' => siteSettings(), 'qr' => $qr,
            'background' => null, 'signature' => null, 'logo' => null,
        ])->render();
        $this->assertStringContainsString('Citra Lestari', $html);
        $this->assertStringContainsString('OF PARTICIPATION', $html);
        $this->assertStringContainsString($certificate->number, $html);

        $this->actingAs($certificate->author, 'author')
            ->get(route('author.certificates.download', $certificate))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_verification_page_shows_valid_revoked_and_unknown_codes(): void
    {
        $this->presenter(paid: true, fullPaper: true, writers: [['Rina Saputri', 'Unhas']]);
        app(CertificateIssuer::class)->issuePending($this->edition);
        $certificate = Certificate::sole();

        $this->get(route('certificates.verify', $certificate->verification_token))
            ->assertOk()
            ->assertSee('Valid certificate')
            ->assertSee('Rina Saputri')
            ->assertSee($certificate->number)
            ->assertSee('noindex', false);

        app(CertificateIssuer::class)->revoke($certificate, 'Salah nama');
        $this->get(route('certificates.verify', $certificate->verification_token))
            ->assertOk()
            ->assertSee('has been revoked')
            ->assertDontSee('Valid certificate');

        $this->get(route('certificates.verify', str_repeat('x', 40)))
            ->assertOk()
            ->assertSee('Certificate not found');
    }

    public function test_reissuing_keeps_the_number_but_invalidates_the_old_qr(): void
    {
        $paper = $this->presenter(paid: true, fullPaper: true, writers: [['Rina Saputri', 'Unhas']]);
        app(CertificateIssuer::class)->issuePending($this->edition);
        $certificate = Certificate::sole();
        $oldToken = $certificate->verification_token;
        app(CertificateIssuer::class)->revoke($certificate, 'Salah nama');

        $paper->authors()->first()->update(['name' => 'Rina Saputri, S.E., M.M.']);
        app(CertificateIssuer::class)->reissue($certificate->refresh());
        $certificate->refresh();

        $this->assertSame($paper->submission_number.'/P1', $certificate->number);
        $this->assertSame('Rina Saputri, S.E., M.M.', $certificate->recipient_name);
        $this->assertFalse($certificate->isRevoked());
        $this->assertNotSame($oldToken, $certificate->verification_token);
        $this->get(route('certificates.verify', $oldToken))->assertSee('Certificate not found');
    }

    public function test_downloads_are_limited_to_the_owner_and_hidden_once_revoked(): void
    {
        $this->attendee(paid: true, name: 'Citra Lestari');
        app(CertificateIssuer::class)->issuePending($this->edition);
        $certificate = Certificate::sole();

        $other = $this->attendee(paid: true, name: 'Orang Lain');
        $this->actingAs($other, 'author')
            ->get(route('author.certificates.download', $certificate))
            ->assertForbidden();

        $this->actingAs($certificate->author, 'author')
            ->get(AuthorDashboard::getUrl(panel: 'author'))
            ->assertSee(route('author.certificates.download', $certificate), false);

        app(CertificateIssuer::class)->revoke($certificate, 'Uji');
        $this->actingAs($certificate->author, 'author')
            ->get(route('author.certificates.download', $certificate))
            ->assertNotFound();
        $this->actingAs($certificate->author, 'author')
            ->get(AuthorDashboard::getUrl(panel: 'author'))
            ->assertDontSee(route('author.certificates.download', $certificate), false);
    }

    public function test_admins_issue_from_the_certificates_page(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('admin_registrasi', 'web');
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-cert@example.test', 'password' => 'secret-password']);
        $admin->assignRole('admin_registrasi');
        $this->actingAs($admin, 'web');

        $this->attendee(paid: true, name: 'Citra Lestari');

        Livewire::test(ListCertificates::class)
            ->callAction(TestAction::make('issue'))
            ->assertNotified('1 sertifikat diterbitkan');

        $certificate = Certificate::sole();
        $this->assertSame($admin->id, $certificate->issued_by);

        $this->get(route('admin.certificates.pdf', $certificate))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_custom_template_prints_only_dynamic_data_at_the_saved_positions(): void
    {
        $settings = app(SiteSettings::class);
        $settings->certificate_custom_layout = true;
        $settings->certificate_layout = ['presentation' => ['name' => ['top' => 101, 'size' => 30, 'align' => 'left']]];
        $settings->save();

        $pdf = app(CertificatePdf::class);
        $layout = $pdf->layout('presentation');
        $this->assertSame(101, $layout['name']['top']);
        $this->assertSame('left', $layout['name']['align']);
        // Yang tidak diatur memakai posisi bawaan.
        $this->assertSame(CertificatePdf::DEFAULT_LAYOUT['qr'], $layout['qr']);
        $this->assertSame(CertificatePdf::DEFAULT_LAYOUT['name'], $pdf->layout('participation')['name']);

        $this->presenter(paid: true, fullPaper: true, writers: [['Rina Saputri', 'Unhas']]);
        app(CertificateIssuer::class)->issuePending($this->edition);
        $certificate = Certificate::sole();

        $html = view('pdf.certificate', [
            'certificate' => $certificate, 'settings' => siteSettings(), 'qr' => $pdf->qrDataUri($certificate),
            'background' => 'data:image/png;base64,AAAA', 'signature' => null, 'logo' => null,
            'custom' => true, 'layout' => $layout,
        ])->render();

        $this->assertStringContainsString('Rina Saputri', $html);
        $this->assertStringContainsString('top: 101mm', $html);
        $this->assertStringContainsString($certificate->paper_title, $html);
        $this->assertStringContainsString($certificate->number, $html);
        // Judul dan teks pengantar ada di desain latar, bukan dicetak sistem.
        $this->assertStringNotContainsString('CERTIFICATE', $html);
        $this->assertStringNotContainsString('This is to certify that', $html);
    }

    public function test_the_settings_form_saves_the_custom_layout_per_certificate_type(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('superadmin', 'web');
        $admin = User::create(['name' => 'Super', 'email' => 'super-cert@example.test', 'password' => 'secret-password']);
        $admin->assignRole('superadmin');
        $this->actingAs($admin, 'web');

        Livewire::test(ManageSiteSettings::class)
            ->assertOk()
            ->fillForm([
                'certificate_custom_layout' => true,
                'certificate_layout.presentation.name.top' => 120,
                'certificate_layout.participation.qr.size' => 30,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SiteSettings::class)->refresh();
        $this->assertTrue($settings->certificate_custom_layout);
        $this->assertEquals(120, app(CertificatePdf::class)->layout('presentation')['name']['top']);
        $this->assertEquals(30, app(CertificatePdf::class)->layout('participation')['qr']['size']);
        // Jenis lain tetap memakai posisi bawaannya.
        $this->assertEquals(CertificatePdf::DEFAULT_LAYOUT['name']['top'], app(CertificatePdf::class)->layout('participation')['name']['top']);
    }

    public function test_committee_can_preview_both_templates(): void
    {
        Role::findOrCreate('admin_registrasi', 'web');
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-preview@example.test', 'password' => 'secret-password']);
        $admin->assignRole('admin_registrasi');

        foreach (['presentation', 'participation'] as $type) {
            $this->actingAs($admin, 'web')
                ->get(route('admin.certificates.preview', $type))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }

        Role::findOrCreate('reviewer', 'web');
        $reviewer = User::create(['name' => 'Reviewer', 'email' => 'reviewer-preview@example.test', 'password' => 'secret-password']);
        $reviewer->assignRole('reviewer');
        $this->actingAs($reviewer, 'web')
            ->get(route('admin.certificates.preview', 'presentation'))
            ->assertForbidden();

        $this->assertSame(0, Certificate::count(), 'Pratinjau tidak boleh menerbitkan sertifikat.');
    }

    /** @param  list<array{0: string, 1: string}>  $writers */
    private function presenter(bool $paid, bool $fullPaper, array $writers, ?string $attendance = 'present'): Submission
    {
        $author = $this->account('presenter', $writers[0][0], $paid);
        $submission = Submission::create([
            'edition_id' => $this->edition->id, 'author_id' => $author->id, 'title' => 'Paper of '.$writers[0][0],
            'abstract' => 'Abstract', 'keywords' => ['k'], 'status' => 'accepted', 'loa_issued_at' => now(),
        ]);

        foreach ($writers as $index => [$name, $affiliation]) {
            $submission->authors()->create([
                'name' => $name, 'email' => 'writer'.$index.'-'.uniqid().'@example.test',
                'affiliation' => $affiliation, 'is_corresponding' => $index === 0, 'order' => $index + 1,
            ]);
        }

        if ($fullPaper) {
            $docx = app(AbstractWordDocument::class)->create($submission);
            $submission->addMedia(UploadedFile::fake()->createWithContent('naskah.docx', file_get_contents($docx)))
                ->toMediaCollection('camera_ready');
        }

        // Kehadiran dicatat moderator di room presentasi (syarat sertifikat presenter).
        $room = PresentationRoom::firstOrCreate(['edition_id' => $this->edition->id, 'name' => 'Room 1']);
        RoomPresentation::create([
            'presentation_room_id' => $room->id, 'submission_id' => $submission->id,
            'attendance' => $attendance, 'presenter_name' => $attendance ? $writers[0][0] : null,
        ]);

        return $submission->refresh();
    }

    private function attendee(bool $paid, string $name): Author
    {
        return $this->account('participant', $name, $paid);
    }

    private function account(string $type, string $name, bool $paid): Author
    {
        $author = Author::create([
            'name' => $name, 'email' => fake()->unique()->safeEmail(), 'password' => 'secret-password',
            'affiliation' => 'Kampus '.$name, 'participation_type' => $type, 'registrant_category' => 'general',
        ]);

        $fee = RegistrationFee::firstOrCreate(
            ['edition_id' => $this->edition->id, 'audience' => $type, 'registrant_category' => 'general'],
            ['category' => ['en' => ucfirst($type)], 'price_regular' => 50_000, 'currency' => 'IDR'],
        );

        Registration::create([
            'edition_id' => $this->edition->id, 'author_id' => $author->id, 'registration_fee_id' => $fee->id,
            'amount' => 50_000, 'currency' => 'IDR', 'payment_method' => 'manual',
            'status' => $paid ? 'paid' : 'pending', 'paid_at' => $paid ? now() : null,
        ]);

        return $author;
    }
}
