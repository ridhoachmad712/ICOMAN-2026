<?php

namespace Tests\Feature;

use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Panitia mengunduh data inti peserta (nama, afiliasi, email, nomor HP) dari
 * halaman Progres Peserta, mengikuti tab yang sedang dibuka.
 */
class ParticipantContactExportTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);

        Role::findOrCreate('admin_registrasi', 'web');
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-export@example.test', 'password' => 'secret-password']);
        $admin->assignRole('admin_registrasi');
        $this->actingAs($admin, 'web');
    }

    public function test_the_download_contains_the_core_contact_data(): void
    {
        $paid = $this->author('Rina Saputri', 'participant', '081234567890', 'Universitas Hasanuddin');
        $this->registration($paid, 'paid');
        $this->author('Budi Santoso', 'presenter', '+6285511122233', 'Universitas Negeri Makassar');

        $rows = $this->download();

        $this->assertSame(['No', 'Nama', 'Afiliasi', 'Email', 'No. HP / WhatsApp', 'Negara', 'Jalur', 'Kategori', 'Status pembayaran'], $rows[0]);
        $byName = collect(array_slice($rows, 1))->keyBy(fn (array $row) => $row[1]);

        $this->assertSame('Universitas Hasanuddin', $byName['Rina Saputri'][2]);
        $this->assertSame($paid->email, $byName['Rina Saputri'][3]);
        // Nomor HP tetap teks: angka 0 di depan tidak hilang.
        $this->assertSame('081234567890', $byName['Rina Saputri'][4]);
        $this->assertSame('Lunas', $byName['Rina Saputri'][8]);
        $this->assertSame('+6285511122233', $byName['Budi Santoso'][4]);
        $this->assertSame('Belum ada invoice', $byName['Budi Santoso'][8]);
    }

    public function test_the_download_follows_the_active_tab(): void
    {
        $this->author('Rina Saputri', 'participant', '081234567890', 'Universitas Hasanuddin');
        $this->author('Budi Santoso', 'presenter', '085511122233', 'Universitas Negeri Makassar');

        $names = collect(array_slice($this->download('participant'), 1))->pluck(1)->all();

        $this->assertSame(['Rina Saputri'], $names);
    }

    public function test_the_treasurer_cannot_reach_participant_data(): void
    {
        Role::findOrCreate('bendahara', 'web');
        $treasurer = User::create(['name' => 'Bendahara', 'email' => 'bendahara-export@example.test', 'password' => 'secret-password']);
        $treasurer->assignRole('bendahara');
        $this->actingAs($treasurer, 'web');

        $this->assertFalse(\App\Filament\Resources\Authors\AuthorResource::canAccess());
    }

    /** @return list<list<string|int|float>> */
    private function download(?string $tab = null): array
    {
        $component = Livewire::test(ListAuthors::class);
        if ($tab) {
            $component->set('activeTab', $tab);
        }

        $component->callAction(TestAction::make('exportContacts')->table())->assertHasNoErrors();

        $download = $component->effects['download'] ?? null;
        $this->assertNotNull($download, 'Tidak ada berkas yang diunduh.');
        $this->assertStringEndsWith('.xlsx', $download['name']);

        $path = tempnam(sys_get_temp_dir(), 'xlsx-test-');
        file_put_contents($path, base64_decode($download['content']));

        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            break;
        }
        $reader->close();
        unlink($path);

        return $rows;
    }

    private function author(string $name, string $type, string $phone, string $affiliation): Author
    {
        return Author::create([
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
            'phone' => $phone,
            'affiliation' => $affiliation,
            'country' => 'ID',
            'participation_type' => $type,
            'registrant_category' => 'general',
        ]);
    }

    private function registration(Author $author, string $status): Registration
    {
        $fee = RegistrationFee::create([
            'edition_id' => $this->edition->id,
            'category' => ['en' => 'Participant'],
            'audience' => $author->participation_type,
            'registrant_category' => 'general',
            'price_regular' => 50_000,
            'currency' => 'IDR',
        ]);

        return Registration::create([
            'edition_id' => $this->edition->id,
            'author_id' => $author->id,
            'registration_fee_id' => $fee->id,
            'amount' => 50_000,
            'currency' => 'IDR',
            'payment_method' => 'manual',
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : null,
        ]);
    }
}
