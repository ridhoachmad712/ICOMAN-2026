<?php

namespace Tests\Feature;

use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Models\Author;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Akun author kini dikelola dari admin, dan reset mandiri di portal sudah ditiadakan. */
class AdminAuthorAccountsTest extends TestCase
{
    public function test_superadmin_sees_the_author_accounts_list(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = $this->superadmin();
        $author = $this->author();

        $this->actingAs($admin, 'web');

        Livewire::test(ListAuthors::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$author])
            ->assertSee($author->email);
    }

    public function test_only_superadmin_can_open_author_accounts(): void
    {
        foreach (['superadmin' => true, 'admin_registrasi' => false, 'content_admin' => false, 'reviewer' => false] as $role => $expected) {
            Role::findOrCreate($role, 'web');
            $user = User::create(['name' => $role, 'email' => $role.'@example.test', 'password' => 'secret-password']);
            $user->assignRole($role);

            $this->actingAs($user, 'web');
            $this->assertSame($expected, AuthorResource::canAccess(), "Akses untuk [{$role}] tidak sesuai.");
        }
    }

    public function test_admin_reset_lets_the_author_log_in_with_the_new_password(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = $this->superadmin();
        $author = $this->author();
        $oldHash = $author->password;

        $this->actingAs($admin, 'web');

        Livewire::test(ListAuthors::class)
            ->callAction(
                TestAction::make('resetPassword')->table($author),
                data: ['password' => 'BrandNewPass123!'],
            )
            ->assertHasNoActionErrors();

        $author->refresh();
        $this->assertNotSame($oldHash, $author->password);
        $this->assertTrue(Hash::check('BrandNewPass123!', $author->password));
        $this->assertTrue(auth('author')->attempt(['email' => $author->email, 'password' => 'BrandNewPass123!']));
    }

    public function test_the_self_service_password_reset_is_gone(): void
    {
        $names = collect(Route::getRoutes())->map(fn ($r) => $r->getName())->filter()->all();

        foreach (['author.password.request', 'author.password.reset', 'filament.author.auth.password-reset.request'] as $gone) {
            $this->assertNotContains($gone, $names, "Route [{$gone}] seharusnya sudah dihapus.");
        }

        // Halaman login author tetap bisa dibuka, tanpa tautan lupa password.
        $this->get('/author/login')->assertOk()->assertDontSee('Forgot password', false);
    }

    /**
     * Kolom kategori sebelumnya selalu kosong: formatStateUsing dilewati saat
     * nilainya null, dan bahkan yang terisi tidak pernah tampil. Kategori
     * menentukan tarif, jadi panitia perlu melihatnya.
     */
    public function test_the_list_shows_the_registrant_category(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superadmin(), 'web');

        $student = Author::create([
            'name' => 'Mahasiswa Satu', 'email' => 'mahasiswa@example.test', 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'student_s1',
        ]);

        // Diperiksa pada kolomnya sendiri: nama kategori juga muncul di pilihan
        // filter, jadi assertSee akan hijau walau selnya kosong.
        Livewire::test(ListAuthors::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$student])
            ->assertTableColumnHasDescription('participation_type', Author::CATEGORIES['student_s1'], $student);
    }

    /** Akun tanpa kategori tidak boleh menyisakan sel kosong tanpa penjelasan. */
    public function test_an_account_without_a_category_falls_back_to_a_label(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superadmin(), 'web');

        $blank = Author::create([
            'name' => 'Belum Lengkap', 'email' => 'belum@example.test', 'password' => 'secret-password',
        ]);

        Livewire::test(ListAuthors::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$blank])
            ->assertTableColumnStateSet('participation_type', 'Belum dipilih', $blank);
    }

    /**
     * Co-host punya jalurnya sendiri. Sebelumnya apa pun yang bukan presenter
     * ditulis "Peserta seminar", jadi institusi co-host disebut salah.
     */
    public function test_a_co_host_is_not_labelled_a_seminar_attendee(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superadmin(), 'web');

        $coHost = Author::create([
            'name' => 'Universitas Mitra', 'email' => 'mitra@example.test', 'password' => 'secret-password',
            'participation_type' => 'cohost', 'registrant_category' => 'general',
        ]);

        // Lewat kolomnya, bukan assertSee: "Co-host" juga ada di label tabnya.
        Livewire::test(ListAuthors::class)
            ->set('activeTab', 'cohost')
            ->assertOk()
            ->assertCanSeeTableRecords([$coHost])
            ->assertTableColumnStateSet('participation_type', 'Co-host', $coHost)
            ->assertTableColumnDoesNotHaveDescription('participation_type', Author::CATEGORIES['general'], $coHost);
    }

    /** Tab memisahkan ketiga jalur, dan tidak mencampurnya. */
    public function test_the_tabs_separate_the_paths(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superadmin(), 'web');

        $presenter = $this->author();
        $attendee = Author::create([
            'name' => 'Peserta Dua', 'email' => 'peserta2@example.test', 'password' => 'secret-password',
            'participation_type' => 'participant', 'registrant_category' => 'general',
        ]);

        Livewire::test(ListAuthors::class)
            ->set('activeTab', 'presenter')
            ->assertCanSeeTableRecords([$presenter])
            ->assertCanNotSeeTableRecords([$attendee]);

        Livewire::test(ListAuthors::class)
            ->set('activeTab', 'participant')
            ->assertCanSeeTableRecords([$attendee])
            ->assertCanNotSeeTableRecords([$presenter]);
    }

    private function superadmin(): User
    {
        Role::findOrCreate('superadmin', 'web');
        $admin = User::create(['name' => 'Super', 'email' => 'super@example.test', 'password' => 'secret-password']);
        $admin->assignRole('superadmin');

        return $admin;
    }

    private function author(): Author
    {
        return Author::create([
            'name' => 'Peserta Satu',
            'email' => 'peserta@example.test',
            'password' => 'secret-password',
            'participation_type' => 'presenter',
            'registrant_category' => 'general',
        ]);
    }
}
