<?php

namespace Tests\Feature;

use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\Submission;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\SoftDeletes;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Akun yang "sudah dihapus" ternyata masih menempati alamat emailnya: daftar
 * akun author sama sekali tidak punya aksi hapus, dan pada user aksi itu
 * tersembunyi di halaman edit. Tes ini memastikan barisnya benar-benar hilang
 * sehingga email yang sama bisa dipakai mendaftar lagi.
 */
class AccountDeletionTest extends TestCase
{
    private function superadmin(string $email = 'super-del@example.test'): User
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('superadmin', 'web');
        $user = User::create(['name' => 'Super', 'email' => $email, 'password' => 'secret-password']);
        $user->assignRole('superadmin');
        $this->actingAs($user, 'web');

        return $user;
    }

    public function test_neither_account_table_uses_soft_deletes(): void
    {
        // Soft delete akan menyisakan baris dan tetap memblokir email unik.
        $this->assertNotContains(SoftDeletes::class, class_uses_recursive(User::class));
        $this->assertNotContains(SoftDeletes::class, class_uses_recursive(Author::class));
    }

    public function test_a_superadmin_can_delete_an_author_and_reuse_the_email(): void
    {
        $this->superadmin();
        $edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);

        $author = Author::create([
            'name' => 'Peserta Salah Daftar',
            'email' => 'ulang@example.test',
            'password' => 'secret-password',
            'participation_type' => 'presenter',
            'registrant_category' => 'general',
        ]);
        $submission = Submission::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'title' => 'Judul',
            'abstract' => 'Isi abstrak.',
            'status' => 'draft',
        ]);
        $fee = RegistrationFee::create([
            'edition_id' => $edition->id,
            'category' => ['en' => 'Registration', 'id' => 'Registrasi'],
            'price_regular' => 1_000_000,
            'currency' => 'IDR',
        ]);
        $registration = Registration::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'registration_fee_id' => $fee->id,
            'fee_category' => $author->feeCategory(),
            'amount' => 1_000_000,
            'payment_method' => 'gateway',
            'status' => 'pending',
        ]);

        Livewire::test(ListAuthors::class)
            ->callAction(TestAction::make('delete')->table($author));

        $this->assertDatabaseMissing('authors', ['id' => $author->id]);
        $this->assertDatabaseMissing('submissions', ['id' => $submission->id]);
        $this->assertDatabaseMissing('registrations', ['id' => $registration->id]);

        // Inti keluhannya: email yang sama harus bebas dipakai lagi.
        $this->assertDatabaseCount('authors', 0);
        Author::create([
            'name' => 'Daftar Ulang',
            'email' => 'ulang@example.test',
            'password' => 'secret-password',
            'participation_type' => 'participant',
            'registrant_category' => 'general',
        ]);
        $this->assertDatabaseHas('authors', ['email' => 'ulang@example.test', 'name' => 'Daftar Ulang']);
    }

    public function test_a_reviewer_account_can_be_deleted_from_the_user_list(): void
    {
        $this->superadmin();
        Role::findOrCreate('reviewer', 'web');

        $reviewer = User::create(['name' => 'Reviewer', 'email' => 'reviewer-del@example.test', 'password' => 'secret-password']);
        $reviewer->assignRole('reviewer');

        Livewire::test(ListUsers::class)
            ->callAction(TestAction::make('delete')->table($reviewer));

        $this->assertDatabaseMissing('users', ['id' => $reviewer->id]);

        User::create(['name' => 'Reviewer Baru', 'email' => 'reviewer-del@example.test', 'password' => 'secret-password']);
        $this->assertDatabaseHas('users', ['email' => 'reviewer-del@example.test', 'name' => 'Reviewer Baru']);
    }

    /** Pagar: jangan sampai superadmin mengunci dirinya sendiri keluar. */
    public function test_a_superadmin_cannot_delete_their_own_account(): void
    {
        $actor = $this->superadmin();

        $this->assertFalse(UserResource::canDelete($actor));
    }

    /** Pagar: satu superadmin terakhir tidak boleh dihapus. */
    public function test_the_last_superadmin_cannot_be_deleted(): void
    {
        $this->superadmin();
        $other = User::create(['name' => 'Super 2', 'email' => 'super2-del@example.test', 'password' => 'secret-password']);
        $other->assignRole('superadmin');

        // Masih ada dua superadmin — yang satu boleh dihapus.
        $this->assertTrue(UserResource::canDelete($other));

        $other->removeRole('superadmin');
        $other->refresh();
        $this->assertSame(1, UserResource::superadminCount());

        $remaining = User::role('superadmin')->first();
        $this->actingAs($other, 'web');
        $this->assertFalse(UserResource::canDelete($remaining));
    }

    /** Non-superadmin tidak boleh menghapus akun siapa pun. */
    public function test_other_roles_cannot_delete_accounts(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('admin_registrasi', 'web');
        Role::findOrCreate('superadmin', 'web');

        $admin = User::create(['name' => 'Admin Reg', 'email' => 'adminreg-del@example.test', 'password' => 'secret-password']);
        $admin->assignRole('admin_registrasi');
        $target = User::create(['name' => 'Super', 'email' => 'target-del@example.test', 'password' => 'secret-password']);
        $target->assignRole('superadmin');

        $this->actingAs($admin, 'web');

        $this->assertFalse(UserResource::canDelete($target));
        $this->assertFalse(AuthorResource::canDeleteAny());
    }
}
