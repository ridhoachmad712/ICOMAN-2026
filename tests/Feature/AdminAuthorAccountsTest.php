<?php

namespace Tests\Feature;

use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Models\Author;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
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
                \Filament\Actions\Testing\TestAction::make('resetPassword')->table($author),
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
        $names = collect(\Illuminate\Support\Facades\Route::getRoutes())->map(fn ($r) => $r->getName())->filter()->all();

        foreach (['author.password.request', 'author.password.reset', 'filament.author.auth.password-reset.request'] as $gone) {
            $this->assertNotContains($gone, $names, "Route [{$gone}] seharusnya sudah dihapus.");
        }

        // Halaman login author tetap bisa dibuka, tanpa tautan lupa password.
        $this->get('/author/login')->assertOk()->assertDontSee('Forgot password', false);
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
