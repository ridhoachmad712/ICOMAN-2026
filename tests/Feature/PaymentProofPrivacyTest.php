<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Edition;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Bukti transfer memuat nama dan nomor rekening pembayar. Dulu tersimpan di
 * disk publik dengan URL /storage/{id}/... yang bisa ditebak; kini privat dan
 * hanya terbuka bagi peran pengurus uang.
 */
class PaymentProofPrivacyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('payment_proofs');
        Storage::fake('public');
    }

    public function test_a_new_proof_lands_on_the_private_disk(): void
    {
        $registration = $this->registrationWithProof();
        $media = $registration->getFirstMedia('payment_proof');

        $this->assertSame('payment_proofs', $media->disk);
        Storage::disk('payment_proofs')->assertExists($media->getPathRelativeToRoot());
        Storage::disk('public')->assertMissing($media->getPathRelativeToRoot());
        $this->assertFalse(config('filesystems.disks.payment_proofs.serve'));
    }

    public function test_money_roles_can_open_the_proof(): void
    {
        $registration = $this->registrationWithProof();

        foreach (['superadmin', 'admin_registrasi', 'bendahara'] as $role) {
            $this->actingAs($this->user($role), 'web')
                ->get(route('admin.registrations.payment-proof', $registration))
                ->assertOk()
                ->assertHeader('X-Content-Type-Options', 'nosniff');
        }
    }

    public function test_other_roles_authors_and_guests_cannot_open_the_proof(): void
    {
        $registration = $this->registrationWithProof();
        $url = route('admin.registrations.payment-proof', $registration);

        foreach (['reviewer', 'content_admin'] as $role) {
            $this->actingAs($this->user($role), 'web')->get($url)->assertForbidden();
        }

        auth('web')->logout();
        // Pemilik invoice pun tidak lewat pintu admin ini.
        $this->actingAs($registration->author, 'author')->get($url)->assertForbidden();

        auth('author')->logout();
        $this->get($url)->assertRedirect();
    }

    public function test_the_command_moves_old_public_proofs_to_private_storage(): void
    {
        $registration = $this->registrationWithProof();
        $media = $registration->getFirstMedia('payment_proof');
        $path = $media->getPathRelativeToRoot();

        // Keadaan lama: berkas di disk publik.
        Storage::disk('public')->put($path, Storage::disk('payment_proofs')->get($path));
        Storage::disk('payment_proofs')->delete($path);
        $media->update(['disk' => 'public', 'conversions_disk' => 'public']);

        $this->artisan('icoman:protect-payment-proofs')->assertSuccessful();
        Storage::disk('public')->assertExists($path);

        $this->artisan('icoman:protect-payment-proofs', ['--fix' => true])->assertSuccessful();

        $this->assertSame('payment_proofs', $media->fresh()->disk);
        Storage::disk('payment_proofs')->assertExists($path);
        Storage::disk('public')->assertMissing($path);

        // Dijalankan ulang tidak mengubah apa pun.
        $this->artisan('icoman:protect-payment-proofs', ['--fix' => true])->assertSuccessful();
        Storage::disk('payment_proofs')->assertExists($path);
    }

    private function registrationWithProof(): Registration
    {
        $edition = Edition::firstOrCreate(['name' => 'ICOMAN 2026'], ['is_active' => true]);

        $author = Author::create([
            'name' => 'Peserta',
            'email' => 'peserta-'.uniqid().'@example.test',
            'password' => 'secret-password',
            'participation_type' => 'participant',
            'registrant_category' => 'general',
        ]);

        $fee = RegistrationFee::create([
            'edition_id' => $edition->id,
            'category' => ['en' => 'Participant'],
            'audience' => 'participant',
            'registrant_category' => 'general',
            'price_regular' => 500_000,
            'currency' => 'IDR',
        ]);

        $registration = Registration::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'registration_fee_id' => $fee->id,
            'amount' => 500_000,
            'currency' => 'IDR',
            'payment_method' => 'manual',
            'status' => 'pending',
        ]);

        $registration->addMedia(UploadedFile::fake()->createWithContent('bukti.pdf', '%PDF-1.4 bukti'))
            ->toMediaCollection('payment_proof');

        return $registration->refresh();
    }

    private function user(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::create([
            'name' => ucfirst($role),
            'email' => $role.'-'.uniqid().'@example.test',
            'password' => 'secret-password',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
