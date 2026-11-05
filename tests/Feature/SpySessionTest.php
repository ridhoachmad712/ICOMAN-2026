<?php

namespace Tests\Feature;

use App\Exceptions\SpyReadOnlyViolation;
use App\Filament\Author\Pages\AuthorProfile;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\User;
use App\Services\SpySession;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SpySessionTest extends TestCase
{
    private Edition $edition;

    private Author $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        RegistrationFee::create([
            'edition_id' => $this->edition->id, 'category' => ['en' => 'Participant'],
            'audience' => 'participant', 'registrant_category' => 'general',
            'price_regular' => 100_000, 'currency' => 'IDR',
        ]);
        $this->author = Author::create([
            'name' => 'Peserta Dilihat', 'email' => 'dilihat@example.test', 'password' => 'secret-password',
            'participation_type' => 'participant', 'registrant_category' => 'general',
        ]);
    }

    public function test_the_action_opens_the_portal_without_a_password(): void
    {
        $this->admin();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListAuthors::class)
            ->callAction(TestAction::make('viewAsAuthor')->table($this->author))
            ->assertRedirect();

        $this->assertSame($this->author->id, Auth::guard('author')->id());
        $this->assertTrue(app(SpySession::class)->active());

        $this->get('/author')->assertOk()
            ->assertSee('Mode lihat')
            ->assertSee('Peserta Dilihat');
    }

    public function test_only_committee_who_manage_participants_can_start(): void
    {
        $this->assertTrue(SpySession::canStart($this->user('superadmin')));
        $this->assertTrue(SpySession::canStart($this->user('admin_registrasi')));
        $this->assertFalse(SpySession::canStart($this->user('reviewer')));
        $this->assertFalse(SpySession::canStart($this->user('bendahara')));
        $this->assertFalse(SpySession::canStart(null));
    }

    public function test_portal_forms_are_refused_and_pages_stay_readable(): void
    {
        $invoice = $this->invoice();
        $this->startViewing();

        $this->post(route('author.registration.voucher', $invoice), ['voucher_code' => 'APAPUN'])
            ->assertRedirect();
        $this->post(route('author.registration.pay', $invoice))->assertRedirect();

        $this->assertSame('pending', $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->voucher_id);
        $this->assertSame(0, $invoice->payments()->count());

        $this->get('/author')->assertOk();
    }

    public function test_a_page_that_would_write_on_open_is_refused(): void
    {
        $this->startViewing();

        $this->get(route('author.registration.checkout'))->assertForbidden();

        $this->assertSame(0, Registration::count());
    }

    public function test_the_database_lock_refuses_every_write_except_framework_tables(): void
    {
        $spy = app(SpySession::class);
        $spy->lock();

        Cache::put('kunci-tes', 'boleh', 60);
        $this->assertSame('boleh', Cache::get('kunci-tes'));
        $this->assertSame($this->author->id, Author::find($this->author->id)->id);

        foreach ([
            fn () => $this->author->update(['name' => 'Diubah']),
            fn () => Author::query()->update(['name' => 'Diubah']),
            fn () => DB::table('authors')->delete(),
            fn () => DB::statement('DROP TABLE authors'),
        ] as $write) {
            try {
                $write();
                $this->fail('Penulisan seharusnya ditolak.');
            } catch (SpyReadOnlyViolation) {
                $this->addToAssertionCount(1);
            }
        }

        $spy->unlock();
        $this->assertSame('Peserta Dilihat', Author::find($this->author->id)->name);
        $this->author->update(['name' => 'Boleh setelah selesai']);
        $this->assertSame('Boleh setelah selesai', $this->author->fresh()->name);
    }

    public function test_saving_the_profile_is_refused_with_a_notice(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('author'));
        $this->actingAs($this->author, 'author');
        app(SpySession::class)->lock();

        Livewire::test(AuthorProfile::class)
            ->set('data.name', 'Nama Baru')
            ->call('save')
            ->assertNotified('Mode lihat: perubahan tidak disimpan');

        app(SpySession::class)->unlock();
        $this->assertSame('Peserta Dilihat', $this->author->fresh()->name);
    }

    public function test_leaving_keeps_the_admin_logged_in_and_touches_nothing_on_the_account(): void
    {
        $this->author->forceFill(['remember_token' => 'token-asli'])->save();
        $this->startViewing();

        $this->post(route('spy.stop'))->assertRedirect(ListAuthors::getUrl(panel: 'admin'));

        $this->assertFalse(app(SpySession::class)->active());
        $this->assertNull(Auth::guard('author')->user());
        $this->assertNotNull(Auth::guard('web')->user());
        $this->assertSame('token-asli', $this->author->fresh()->remember_token);
    }

    public function test_the_portal_logout_button_only_ends_the_session(): void
    {
        $this->author->forceFill(['remember_token' => 'token-asli'])->save();
        $this->startViewing();

        $this->post(route('filament.author.auth.logout'))->assertRedirect(ListAuthors::getUrl(panel: 'admin'));

        $this->assertFalse(app(SpySession::class)->active());
        $this->assertNotNull(Auth::guard('web')->user());
        $this->assertSame('token-asli', $this->author->fresh()->remember_token);
    }

    public function test_the_session_ends_when_the_admin_loses_the_role(): void
    {
        $admin = $this->startViewing();
        $admin->syncRoles([]);
        $admin->unsetRelation('roles');

        $this->get('/author');

        $this->assertFalse(app(SpySession::class)->active());
        $this->assertNull(Auth::guard('author')->user());
    }

    public function test_the_admin_panel_is_not_locked_during_a_session(): void
    {
        $this->startViewing();

        $this->get(ListAuthors::getUrl(panel: 'admin'))->assertOk();

        $this->assertFalse(app(SpySession::class)->isLocked());
    }

    private function startViewing(): User
    {
        $admin = $this->admin();
        app(SpySession::class)->start($admin, $this->author);

        return $admin;
    }

    private function admin(): User
    {
        $admin = $this->user('admin_registrasi');
        $this->actingAs($admin, 'web');

        return $admin;
    }

    private function user(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::create(['name' => 'Panitia '.$role, 'email' => $role.'-spy@example.test', 'password' => 'secret-password']);
        $user->assignRole($role);

        return $user;
    }

    private function invoice(): Registration
    {
        return Registration::create([
            'edition_id' => $this->edition->id, 'author_id' => $this->author->id,
            'registration_fee_id' => RegistrationFee::first()->id, 'payment_method' => 'gateway',
            'amount' => 100_000, 'currency' => 'IDR', 'status' => 'pending',
        ]);
    }
}
