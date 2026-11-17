<?php

namespace Tests\Feature;

use App\Filament\Author\Pages\AuthorDashboard;
use App\Filament\Author\Pages\AuthorProfile;
use App\Filament\Pages\Settings\ManageSiteSettings;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Resources\Certificates\Pages\ListCertificates;
use App\Livewire\CertificateZipStatus;
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
use App\Services\CertificateZipExport;
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
        // Presenter yang paper-nya dipresentasikan juga menerima Certificate of
        // Participation; presenter yang gugur syarat tidak menerima keduanya.
        $this->assertSame(['presentation' => 2, 'participation' => 2], $issuer->pendingCounts($this->edition));
        $this->assertSame(4, $issuer->issuePending($this->edition));

        $presentations = Certificate::where('type', 'presentation')->orderBy('number')->get();
        $this->assertSame(['Rina Saputri', 'Andi Wijaya'], $presentations->pluck('recipient_name')->all());
        $this->assertSame(['Unhas', 'UNM'], $presentations->pluck('recipient_affiliation')->all());
        $this->assertSame([$paper->submission_number.'/P1', $paper->submission_number.'/P2'], $presentations->pluck('number')->all());
        // Semuanya diunduh lewat akun pengirim paper.
        $this->assertSame([$paper->author_id], $presentations->pluck('author_id')->unique()->values()->all());

        $participations = Certificate::where('type', 'participation')->orderBy('number')->get();
        $this->assertSame(['Citra Lestari', 'Rina Saputri'], $participations->pluck('recipient_name')->all());
        $this->assertSame(['ICOMAN2026/A/0001', 'ICOMAN2026/A/0002'], $participations->pluck('number')->all());
        $this->assertSame([$attendee->id, $paper->author_id], $participations->pluck('author_id')->all());
        // Co-author tidak punya akun: hanya menerima Certificate of Presentation.
        $this->assertFalse($participations->contains('recipient_name', 'Andi Wijaya'));

        // Tombol yang sama ditekan lagi: tidak ada duplikat.
        $this->assertSame(0, $issuer->issuePending($this->edition));
        $this->assertSame(4, Certificate::count());
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
        app(CertificateIssuer::class)->issuePending($this->edition, type: 'presentation');
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
        app(CertificateIssuer::class)->issuePending($this->edition, type: 'presentation');
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

    public function test_the_name_and_affiliation_lock_once_a_certificate_is_issued(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('author'));
        $author = $this->attendee(paid: true, name: 'Citra Lestari');
        $this->actingAs($author, 'author');

        // Sebelum terbit, peserta masih bebas memperbaiki namanya sendiri.
        Livewire::test(AuthorProfile::class)
            ->assertFormFieldEnabled('name')
            ->fillForm(['name' => 'Citra Lestari, S.E.'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Citra Lestari, S.E.', $author->refresh()->name);

        app(CertificateIssuer::class)->issuePending($this->edition);

        Livewire::test(AuthorProfile::class)
            ->assertFormFieldDisabled('name')
            ->assertFormFieldDisabled('affiliation')
            // Kiriman yang direkayasa pun tidak mengubah nama.
            ->set('data.name', 'Nama Orang Lain')
            ->set('data.affiliation', 'Kampus Lain')
            ->set('data.phone', '081200000000')
            ->call('save');

        $author->refresh();
        $this->assertSame('Citra Lestari, S.E.', $author->name);
        $this->assertSame('Kampus Citra Lestari', $author->affiliation);
        $this->assertSame('081200000000', $author->phone, 'Isian lain tetap bisa diubah.');
    }

    public function test_the_committee_corrects_the_name_then_reissues(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('admin_registrasi', 'web');
        $admin = User::create(['name' => 'Admin', 'email' => 'identity-admin@example.test', 'password' => 'secret-password']);
        $admin->assignRole('admin_registrasi');
        $this->actingAs($admin, 'web');

        $author = $this->attendee(paid: true, name: 'Citra Lestar');
        app(CertificateIssuer::class)->issuePending($this->edition);
        $certificate = Certificate::sole();

        Livewire::test(ListAuthors::class)
            ->callAction(TestAction::make('editIdentity')->table($author), ['name' => 'Citra Lestari, S.E.', 'affiliation' => 'Universitas Negeri Makassar'])
            ->assertHasNoFormErrors()
            ->assertNotified('Nama dan afiliasi diperbarui');

        $this->assertSame('Citra Lestar', $certificate->refresh()->recipient_name, 'Sertifikat baru berubah setelah terbit ulang.');

        app(CertificateIssuer::class)->reissue($certificate, $admin);

        $certificate->refresh();
        $this->assertSame('Citra Lestari, S.E.', $certificate->recipient_name);
        $this->assertSame('Universitas Negeri Makassar', $certificate->recipient_affiliation);
    }

    public function test_admins_revoke_every_issued_certificate_then_restore_them(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('admin_registrasi', 'web');
        $admin = User::create(['name' => 'Admin', 'email' => 'revoke-all@example.test', 'password' => 'secret-password']);
        $admin->assignRole('admin_registrasi');
        $this->actingAs($admin, 'web');

        $attendee = $this->attendee(paid: true, name: 'Citra Lestari');
        $this->presenter(paid: true, fullPaper: true, writers: [['Rina Saputri', 'Unhas'], ['Budi Hartono', 'UNM']]);
        app(CertificateIssuer::class)->issuePending($this->edition);
        $this->assertSame(4, Certificate::valid()->count());

        // Tanpa kata konfirmasi yang benar, tidak ada yang dicabut.
        Livewire::test(ListCertificates::class)
            ->callAction(TestAction::make('revokeAll'), ['scope' => 'all', 'reason' => 'Template salah', 'confirmation' => 'cabut'])
            ->assertHasFormErrors(['confirmation']);
        $this->assertSame(4, Certificate::valid()->count());

        // Satu jenis saja.
        Livewire::test(ListCertificates::class)
            ->callAction(TestAction::make('revokeAll'), ['scope' => 'presentation', 'reason' => 'Template salah', 'confirmation' => 'CABUT'])
            ->assertHasNoFormErrors()
            ->assertNotified('2 sertifikat dicabut');
        $this->assertSame(['participation'], Certificate::valid()->pluck('type')->unique()->values()->all());

        Livewire::test(ListCertificates::class)
            ->callAction(TestAction::make('revokeAll'), ['scope' => 'all', 'reason' => 'Template salah', 'confirmation' => 'CABUT'])
            ->assertNotified('2 sertifikat dicabut');
        $this->assertSame(0, Certificate::valid()->count());
        $this->assertSame(['Template salah'], Certificate::pluck('revoke_reason')->unique()->values()->all());

        // Tombol terbitkan biasa menawarkan penerbitan ulang; bila dimatikan, tetap dicabut.
        Livewire::test(ListCertificates::class)
            ->callAction(TestAction::make('issuePresentation'), ['include_revoked' => false])
            ->assertNotified('Tidak ada sertifikat presenter baru');
        $this->assertSame(0, Certificate::valid()->count());

        Livewire::test(ListCertificates::class)
            ->callAction(TestAction::make('issuePresentation'), ['include_revoked' => true])
            ->assertNotified('2 diterbitkan ulang');
        $this->assertSame(['presentation'], Certificate::valid()->pluck('type')->unique()->values()->all());

        $attendee->update(['name' => 'Citra Lestari, S.E.']);
        $numbers = Certificate::orderBy('id')->pluck('number')->all();
        Livewire::test(ListCertificates::class)
            ->callAction(TestAction::make('reissueRevoked'), ['scope' => 'all'])
            ->assertNotified('2 sertifikat diterbitkan ulang');

        $this->assertSame(4, Certificate::valid()->count());
        $this->assertSame($numbers, Certificate::orderBy('id')->pluck('number')->all(), 'Nomor tetap.');
        $this->assertSame('Citra Lestari, S.E.', Certificate::where('author_id', $attendee->id)->sole()->recipient_name);
    }

    public function test_admins_create_manual_certificates(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('admin_registrasi', 'web');
        $admin = User::create(['name' => 'Admin', 'email' => 'manual-cert@example.test', 'password' => 'secret-password']);
        $admin->assignRole('admin_registrasi');
        $this->actingAs($admin, 'web');
        $this->attendee(paid: true, name: 'Citra Lestari');
        app(CertificateIssuer::class)->issuePending($this->edition);

        // Presenter wajib judul artikel.
        Livewire::test(ListCertificates::class)
            ->callAction(TestAction::make('issueManual'), ['type' => 'presentation', 'recipient_name' => 'Dr. Budi'])
            ->assertHasFormErrors(['paper_title' => 'required']);

        Livewire::test(ListCertificates::class)
            ->callAction(TestAction::make('issueManual'), [
                'type' => 'presentation', 'recipient_name' => 'Dr. Budi Hartono', 'recipient_affiliation' => 'UNM',
                'paper_title' => 'Green Marketing in Makassar',
            ])
            ->assertHasNoFormErrors()
            ->assertNotified('Sertifikat ICOMAN2026/P/0001 diterbitkan');

        // Peserta melanjutkan urutan /A/; judul diabaikan.
        Livewire::test(ListCertificates::class)
            ->callAction(TestAction::make('issueManual'), [
                'type' => 'participation', 'recipient_name' => 'Sari', 'paper_title' => 'Tidak dipakai',
            ])
            ->assertHasNoFormErrors();

        // Nomor yang diisi sendiri tidak boleh bentrok.
        Livewire::test(ListCertificates::class)
            ->callAction(TestAction::make('issueManual'), ['type' => 'participation', 'recipient_name' => 'Tono', 'number' => 'ICOMAN2026/A/0001'])
            ->assertHasFormErrors(['number' => 'unique']);

        $presenter = Certificate::where('number', 'ICOMAN2026/P/0001')->sole();
        $this->assertTrue($presenter->isManual());
        $this->assertNull($presenter->author_id);
        $this->assertSame(['Dr. Budi Hartono', 'UNM', 'Green Marketing in Makassar'], [$presenter->recipient_name, $presenter->recipient_affiliation, $presenter->paper_title]);

        $participant = Certificate::where('recipient_name', 'Sari')->sole();
        $this->assertSame('ICOMAN2026/A/0002', $participant->number);
        $this->assertNull($participant->paper_title);

        // Koreksi langsung, dan terbit ulang tidak menimpa data yang diisi tangan.
        Livewire::test(ListCertificates::class)
            ->set('activeTab', 'manual')
            ->callTableAction('editManual', $presenter, ['recipient_name' => 'Dr. Budi Hartono, M.M.', 'recipient_affiliation' => 'UNM', 'paper_title' => 'Green Marketing'])
            ->assertHasNoTableActionErrors();
        app(CertificateIssuer::class)->reissue($presenter->refresh(), $admin);
        $this->assertSame('Dr. Budi Hartono, M.M.', $presenter->refresh()->recipient_name);
        Livewire::test(ListCertificates::class)
            ->assertTableActionHidden('editManual', Certificate::where('recipient_name', 'Citra Lestari')->sole());

        $this->get(route('admin.certificates.pdf', $presenter))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('certificates.verify', $presenter->verification_token))->assertSee('Dr. Budi Hartono, M.M.');
    }

    public function test_admins_bulk_import_manual_certificates_from_csv(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('admin_registrasi', 'web');
        $admin = User::create(['name' => 'Admin', 'email' => 'import-cert@example.test', 'password' => 'secret-password']);
        $admin->assignRole('admin_registrasi');
        $this->actingAs($admin, 'web');

        $this->get(route('admin.certificates.import-template'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertSee('jenis;nama;afiliasi;judul;nomor', false);

        // Satu baris salah: tidak ada yang diterbitkan, kesalahannya disebut per baris.
        $broken = "jenis;nama;afiliasi;judul;nomor\npresenter;Dr. Budi;UNM;;\nhadirin;Sari;UH;;\n";
        Livewire::test(ListCertificates::class)
            ->callAction(TestAction::make('importManual'), ['file' => UploadedFile::fake()->createWithContent('sertifikat.csv', $broken)])
            ->assertNotified('Impor dibatalkan: 2 kesalahan');
        $this->assertSame(0, Certificate::count());

        // BOM dari Excel, pemisah koma, dan judul berkoma yang dikutip.
        $csv = "\u{FEFF}Jenis,Nama,Afiliasi,Judul,Nomor\n"
            ."presenter,\"Dr. Budi Hartono, M.M.\",UNM,\"Green Marketing, Loyalty, and Trust\",\n"
            ."Peserta,Sari Wulandari,Universitas Hasanuddin,diabaikan,\n"
            .",,,,\n"
            ."peserta,Tono,,,SERT/KHUSUS/01\n";
        Livewire::test(ListCertificates::class)
            ->callAction(TestAction::make('importManual'), ['file' => UploadedFile::fake()->createWithContent('sertifikat.csv', $csv)])
            ->assertNotified('3 sertifikat manual diterbitkan');

        $budi = Certificate::where('number', 'ICOMAN2026/P/0001')->sole();
        $this->assertSame(['Dr. Budi Hartono, M.M.', 'UNM', 'Green Marketing, Loyalty, and Trust'], [$budi->recipient_name, $budi->recipient_affiliation, $budi->paper_title]);
        $this->assertTrue($budi->isManual());
        $sari = Certificate::where('recipient_name', 'Sari Wulandari')->sole();
        $this->assertSame(['participation', 'ICOMAN2026/A/0001', null], [$sari->type, $sari->number, $sari->paper_title]);
        $this->assertSame('SERT/KHUSUS/01', Certificate::where('recipient_name', 'Tono')->sole()->number);

        // Nomor yang sudah terpakai ditolak.
        Livewire::test(ListCertificates::class)
            ->callAction(TestAction::make('importManual'), ['file' => UploadedFile::fake()->createWithContent('lagi.csv', "jenis;nama;nomor\npeserta;Ani;SERT/KHUSUS/01\n")])
            ->assertNotified('Impor dibatalkan: 1 kesalahan');
        $this->assertSame(3, Certificate::count());
    }

    public function test_manual_certificates_are_listed_apart_and_download_as_a_zip(): void
    {
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('admin_registrasi', 'web');
        $admin = User::create(['name' => 'Admin', 'email' => 'zip-cert@example.test', 'password' => 'secret-password']);
        $admin->assignRole('admin_registrasi');
        $this->actingAs($admin, 'web');

        $this->attendee(paid: true, name: 'Citra Lestari');
        $issuer = app(CertificateIssuer::class);
        $issuer->issuePending($this->edition);
        $budi = $issuer->issueManual($this->edition, ['type' => 'presentation', 'recipient_name' => 'Dr. Budi Hartono', 'paper_title' => 'Green Marketing']);
        $sari = $issuer->issueManual($this->edition, ['type' => 'participation', 'recipient_name' => 'Sari Wulandari']);
        $revoked = $issuer->issueManual($this->edition, ['type' => 'participation', 'recipient_name' => 'Dicabut Saja']);
        $issuer->revoke($revoked, 'Uji');
        $account = Certificate::linkedToAccount()->sole();

        Livewire::test(ListCertificates::class)
            ->assertCanSeeTableRecords([$account])
            ->assertCanNotSeeTableRecords([$budi, $sari])
            ->set('activeTab', 'manual')
            ->assertCanSeeTableRecords([$budi, $sari])
            ->assertCanNotSeeTableRecords([$account])
            ->assertActionVisible('downloadManualZip');

        // Dipesan dulu: belum ada yang bisa diunduh.
        Livewire::test(ListCertificates::class)
            ->set('activeTab', 'manual')
            ->callAction(TestAction::make('downloadManualZip'))
            ->assertNotified('ZIP sedang disiapkan');
        Livewire::test(CertificateZipStatus::class)->assertSee('Menyiapkan ZIP: 0 dari 2');
        $this->get(route('admin.certificates.zip'))->assertNotFound();

        // Panel memanggil work() berulang; satu sertifikat per langkah (seolah
        // tiap PDF menghabiskan jatah waktu), tanpa queue worker.
        CertificateZipExport::$budgetSeconds = 0;
        Livewire::test(CertificateZipStatus::class)
            ->call('work')
            ->assertSee('Menyiapkan ZIP: 1 dari 2')
            ->call('work')
            ->assertSee('ZIP siap')
            ->assertSee('Unduh ZIP');
        CertificateZipExport::$budgetSeconds = 20;
        // Diunduh langsung dari web server (disk publik, folder acak).
        $token = app(CertificateZipExport::class)->status($admin)['token'];
        $this->get(route('admin.certificates.zip'))->assertRedirect(app(CertificateZipExport::class)->url($token));
        $this->assertSame(
            ['ICOMAN2026-A-0002 - Sari Wulandari.pdf', 'ICOMAN2026-P-0001 - Dr. Budi Hartono.pdf'],
            $this->zipEntries($token),
        );
        $this->assertTrue(Storage::disk('public')->exists('certificate-zips/index.html'), 'Folder tidak bisa didaftar isinya.');

        // Baris terpilih, termasuk milik akun; pesanan baru menggantikan yang lama.
        $first = app(CertificateZipExport::class)->status($admin)['token'];
        Livewire::test(ListCertificates::class)
            ->set('activeTab', 'manual')
            ->callTableBulkAction('downloadZip', [$budi])
            ->assertNotified('ZIP sedang disiapkan');
        $this->assertFalse(Storage::disk('public')->exists(app(CertificateZipExport::class)->path($first)));
        Livewire::test(CertificateZipStatus::class)->call('work')->assertSee('ZIP siap');
        $status = app(CertificateZipExport::class)->status($admin);
        $this->assertSame(['ICOMAN2026-P-0001 - Dr. Budi Hartono.pdf'], $this->zipEntries($status['token']));

        // Ditutup: berkasnya dibuang.
        Livewire::test(CertificateZipStatus::class)->call('dismiss')->assertDontSee('ZIP siap');
        $this->assertFalse(Storage::disk('public')->exists(app(CertificateZipExport::class)->path($status['token'])));

        // Hanya panitia pengelola submission.
        $this->actingAs(User::create(['name' => 'Lain', 'email' => 'lain-zip@example.test', 'password' => 'secret-password']), 'web')
            ->get(route('admin.certificates.zip'))
            ->assertForbidden();
    }

    /** @return list<string> */
    private function zipEntries(string $token): array
    {
        $zip = new \ZipArchive;
        $zip->open(Storage::disk('public')->path(app(CertificateZipExport::class)->path($token)));
        $names = collect(range(0, $zip->numFiles - 1))->map(fn (int $i) => $zip->getNameIndex($i))->all();
        $this->assertStringStartsWith('%PDF', (string) $zip->getFromIndex(0));
        $zip->close();

        return $names;
    }

    public function test_admins_issue_from_the_certificates_page(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('admin_registrasi', 'web');
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-cert@example.test', 'password' => 'secret-password']);
        $admin->assignRole('admin_registrasi');
        $this->actingAs($admin, 'web');

        $this->attendee(paid: true, name: 'Citra Lestari');
        $this->presenter(paid: true, fullPaper: true, writers: [['Rina Saputri', 'Unhas'], ['Budi Hartono', 'UNM']]);

        // Penerbitan presenter tidak ikut menerbitkan peserta seminar, dan sebaliknya.
        Livewire::test(ListCertificates::class)
            ->callAction(TestAction::make('issueParticipation'))
            ->assertNotified('2 sertifikat peserta seminar diterbitkan');
        $this->assertSame(['participation'], Certificate::pluck('type')->unique()->values()->all());

        Livewire::test(ListCertificates::class)
            ->callAction(TestAction::make('issuePresentation'))
            ->assertNotified('2 sertifikat presenter diterbitkan');
        $this->assertSame(2, Certificate::where('type', 'presentation')->count());

        Livewire::test(ListCertificates::class)
            ->callAction(TestAction::make('issueParticipation'))
            ->assertNotified('Tidak ada sertifikat peserta seminar baru');

        $certificate = Certificate::where('type', 'participation')->where('recipient_name', 'Citra Lestari')->sole();
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
        app(CertificateIssuer::class)->issuePending($this->edition, type: 'presentation');
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

    public function test_custom_template_styles_text_and_hides_chosen_elements(): void
    {
        $settings = app(SiteSettings::class);
        $settings->certificate_custom_layout = true;
        $settings->certificate_layout = ['presentation' => [
            'name' => ['font' => 'sans', 'bold' => '0', 'uppercase' => '1', 'visible' => '0'],
            'affiliation' => ['visible' => '0'],
            'paper_title' => ['quotes' => '0', 'italic' => '0', 'font' => 'mono'],
            'number' => ['prefix' => '-'],
            'qr' => ['visible' => '0'],
        ]];
        $settings->save();

        $pdf = app(CertificatePdf::class);
        $layout = $pdf->layout('presentation');
        $this->assertTrue($layout['name']['visible'], 'Nama penerima selalu dicetak.');
        $this->assertFalse($layout['name']['bold']);
        $this->assertTrue($layout['name']['uppercase']);
        $this->assertFalse($layout['affiliation']['visible']);
        $this->assertTrue($pdf->layout('participation')['affiliation']['visible'], 'Jenis lain tetap memakai bawaan.');

        $this->presenter(paid: true, fullPaper: true, writers: [['Rina Saputri', 'Universitas Hasanuddin']]);
        app(CertificateIssuer::class)->issuePending($this->edition, type: 'presentation');
        $certificate = Certificate::sole();

        $html = view('pdf.certificate', [
            'certificate' => $certificate, 'settings' => siteSettings(), 'qr' => $pdf->qrDataUri($certificate),
            'background' => 'data:image/png;base64,AAAA', 'signature' => null, 'logo' => null,
            'custom' => true, 'layout' => $layout,
        ])->render();

        $this->assertStringContainsString('Rina Saputri', $html);
        $this->assertStringContainsString('text-transform: uppercase', $html);
        $this->assertStringContainsString('font-family: DejaVu Sans Mono', $html);
        $this->assertStringNotContainsString('Universitas Hasanuddin', $html);
        $this->assertStringNotContainsString('alt="QR"', $html);
        $this->assertStringNotContainsString('No. ', $html);
        $this->assertStringNotContainsString('“', $html);
        $this->assertStringContainsString($certificate->number, $html);
    }

    public function test_an_uploaded_font_is_embedded_in_the_pdf(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $background = imagecreatetruecolor(20, 14);
        ob_start();
        imagepng($background);
        Storage::disk('public')->put('certificates/latar.png', ob_get_clean());
        Storage::disk('local')->put('certificates/fonts/merek.ttf', file_get_contents(base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSansMono.ttf')));

        $settings = app(SiteSettings::class);
        $settings->certificate_custom_layout = true;
        $settings->certificate_background_presentation = 'certificates/latar.png';
        $settings->certificate_font = 'certificates/fonts/merek.ttf';
        $settings->certificate_layout = ['presentation' => ['name' => ['font' => 'custom']]];
        $settings->save();

        $this->presenter(paid: true, fullPaper: true, writers: [['Rina Saputri', 'Unhas']]);
        app(CertificateIssuer::class)->issuePending($this->edition, type: 'presentation');

        $content = app(CertificatePdf::class)->download(Certificate::sole())->getContent();

        $this->assertStringStartsWith('%PDF', $content);
        $this->assertStringContainsString('DejaVuSansMono', $content, 'Huruf unggahan disematkan di PDF.');
        // Latar PNG diubah sekali ke JPEG (jauh lebih cepat bagi dompdf) dan dipakai ulang.
        $this->assertCount(1, Storage::disk('local')->files('certificate-cache'));
        $this->assertStringContainsString('/DCTDecode', $content);
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
                'certificate_layout.presentation.affiliation.visible' => '0',
                'certificate_layout.presentation.name.font' => 'mono',
                'certificate_layout.presentation.name.uppercase' => '1',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SiteSettings::class)->refresh();
        $this->assertTrue($settings->certificate_custom_layout);
        $this->assertEquals(120, app(CertificatePdf::class)->layout('presentation')['name']['top']);
        $this->assertEquals(30, app(CertificatePdf::class)->layout('participation')['qr']['size']);
        $this->assertFalse(app(CertificatePdf::class)->layout('presentation')['affiliation']['visible']);
        $this->assertSame('mono', app(CertificatePdf::class)->layout('presentation')['name']['font']);
        $this->assertTrue(app(CertificatePdf::class)->layout('presentation')['name']['uppercase']);
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
