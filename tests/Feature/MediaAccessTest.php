<?php

namespace Tests\Feature;

use App\Models\Edition;
use App\Models\Speaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Foto speaker "berhasil diunggah tapi tidak tampil" berarti URL /storage/...
 * tidak terjangkau — biasanya karena symlink public/storage hilang di server.
 * Disk publik sekarang punya route cadangan (serve), sehingga berkas tetap
 * tersaji lewat PHP walau symlink tidak ada.
 */
class MediaAccessTest extends TestCase
{
    public function test_public_disk_files_are_served_without_a_symlink(): void
    {
        // Route ini yang menyelamatkan saat symlink tidak ada.
        $this->assertNotNull(
            app('router')->getRoutes()->getByName('storage.public'),
            'Route cadangan penyaji berkas publik belum terdaftar.',
        );

        Storage::disk('public')->put('probe/contoh.txt', 'isi-berkas');

        $response = $this->get('/storage/probe/contoh.txt')->assertOk();

        // Respons berkas disajikan sebagai stream/file, jadi isinya dibaca dari objeknya.
        $served = $response->baseResponse instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            ? file_get_contents($response->baseResponse->getFile()->getPathname())
            : $response->streamedContent();

        $this->assertSame('isi-berkas', $served);

        Storage::disk('public')->delete('probe/contoh.txt');
    }

    /** Berkas privat (full paper) tidak boleh ikut tersaji lewat /storage. */
    public function test_the_private_disk_is_not_publicly_served(): void
    {
        $this->assertNull(app('router')->getRoutes()->getByName('storage.local'));
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->assertFalse(config('filesystems.disks.papers.serve'));
    }

    public function test_an_uploaded_speaker_photo_is_reachable(): void
    {
        $edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $speaker = Speaker::create([
            'edition_id' => $edition->id, 'name' => 'Dr Contoh',
            'type' => 'keynote', 'order' => 1, 'is_published' => true,
        ]);

        $image = imagecreatetruecolor(600, 600);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 40, 90));
        $file = sys_get_temp_dir().'/speaker-probe.jpg';
        imagejpeg($image, $file);

        $speaker->addMedia(UploadedFile::fake()->createWithContent('foto.jpg', file_get_contents($file)))
            ->toMediaCollection('photo');
        $speaker->refresh();

        $url = $speaker->getFirstMediaUrl('photo', 'card');
        $this->assertNotSame('', $url, 'URL foto kosong.');

        // Ambil lewat HTTP seperti browser pengunjung.
        $this->get(parse_url($url, PHP_URL_PATH))->assertOk();
    }
}
