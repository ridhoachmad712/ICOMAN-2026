<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\CertificateDesigner;
use App\Models\User;
use App\Services\CertificatePdf;
use App\Settings\SiteSettings;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Editor visual template sertifikat. */
class CertificateDesignerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_only_superadmins_can_open_the_editor(): void
    {
        $this->actingAs($this->user('admin_registrasi'), 'web');

        $this->assertFalse(CertificateDesigner::canAccess());
        $this->get(CertificateDesigner::getUrl())->assertForbidden();
    }

    public function test_the_editor_shows_the_saved_layout_and_the_background(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('certificates/latar.png', 'png');
        $settings = app(SiteSettings::class);
        $settings->certificate_background_presentation = 'certificates/latar.png';
        $settings->certificate_layout = ['participation' => ['name' => ['top' => 51]]];
        $settings->save();
        $this->actingAs($this->user('superadmin'), 'web');

        $this->get(CertificateDesigner::getUrl())
            ->assertOk()
            ->assertSee('Simpan tata letak')
            ->assertSee('Aktifkan template custom');

        // Peserta seminar tanpa latar sendiri memakai latar presenter.
        Livewire::test(CertificateDesigner::class)
            ->assertViewHas('config', fn (array $config): bool => $config['background'] === '/storage/certificates/latar.png'
                && (float) $config['layout']['name']['top'] === 51.0
                && ! array_key_exists('paper_title', $config['labels']));
    }

    public function test_saving_cleans_the_layout_and_keeps_the_other_type(): void
    {
        $settings = app(SiteSettings::class);
        $settings->certificate_layout = ['presentation' => ['name' => ['top' => 99]]];
        $settings->save();
        $this->actingAs($this->user('superadmin'), 'web');

        Livewire::test(CertificateDesigner::class, ['type' => 'participation'])
            ->call('saveLayout', [
                'name' => ['top' => 51.24, 'left' => 20, 'width' => 133, 'size' => 32, 'align' => 'center', 'color' => '#1565D8', 'font' => 'custom', 'bold' => false, 'visible' => false],
                'affiliation' => ['visible' => false, 'top' => 999, 'align' => 'diagonal', 'color' => 'red', 'font' => 'comic'],
                'number' => ['visible' => false, 'prefix' => '<b>Nomor</b>'],
                'qr' => ['top' => 160, 'left' => 262, 'size' => 200],
                'unknown' => ['top' => 1],
            ])
            ->assertNotified();

        $pdf = app(CertificatePdf::class);
        $layout = $pdf->layout('participation');

        $this->assertSame(51.2, $layout['name']['top']);
        $this->assertSame('#1565d8', $layout['name']['color']);
        $this->assertSame('custom', $layout['name']['font']);
        $this->assertFalse($layout['name']['bold']);
        $this->assertTrue($layout['name']['visible'], 'Nama selalu dicetak.');
        $this->assertFalse($layout['affiliation']['visible']);
        $this->assertSame(210.0, $layout['affiliation']['top'], 'Tidak keluar dari kertas.');
        $this->assertSame('center', $layout['affiliation']['align']);
        $this->assertSame(CertificatePdf::DEFAULT_LAYOUT['affiliation']['color'], $layout['affiliation']['color']);
        $this->assertSame('sans', $layout['affiliation']['font']);
        $this->assertSame('Nomor', $layout['number']['prefix']);
        $this->assertSame(80.0, $layout['qr']['size']);
        $this->assertArrayNotHasKey('unknown', app(SiteSettings::class)->refresh()->certificate_layout['participation']);
        $this->assertEquals(99, $pdf->layout('presentation')['name']['top']);
    }

    public function test_the_editor_can_turn_on_the_custom_template(): void
    {
        $this->actingAs($this->user('superadmin'), 'web');

        Livewire::test(CertificateDesigner::class)->call('enableCustomLayout');

        $this->assertTrue(app(SiteSettings::class)->refresh()->certificate_custom_layout);
    }

    public function test_the_uploaded_font_is_served_to_superadmins_only(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('certificates/fonts/merek.ttf', 'ttf-bytes');
        $settings = app(SiteSettings::class);
        $settings->certificate_font = 'certificates/fonts/merek.ttf';
        $settings->save();

        $this->actingAs($this->user('superadmin'), 'web');
        $this->get(route('admin.certificates.font'))->assertOk()->assertHeader('content-type', 'font/ttf');
    }

    public function test_the_font_is_refused_to_other_roles(): void
    {
        $this->actingAs($this->user('admin_registrasi'), 'web');

        $this->get(route('admin.certificates.font'))->assertForbidden();
    }

    private function user(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::create(['name' => 'Panitia '.$role, 'email' => $role.'-designer@example.test', 'password' => 'secret-password']);
        $user->assignRole($role);

        return $user;
    }
}
