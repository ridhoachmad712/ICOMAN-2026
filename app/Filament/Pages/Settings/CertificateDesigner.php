<?php

namespace App\Filament\Pages\Settings;

use App\Models\Certificate;
use App\Services\CertificatePdf;
use App\Settings\SiteSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Editor visual template sertifikat: elemen dinamis digeser langsung di atas
 * gambar latar, lalu disimpan ke pengaturan yang sama dengan isian angka di
 * Site Settings. Kanvas ini perkiraan tampilan di browser; hasil akhir tetap
 * dari PDF (tombol Pratinjau PDF).
 */
class CertificateDesigner extends Page
{
    protected string $view = 'filament.pages.certificate-designer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Editor Sertifikat';

    protected static ?string $navigationLabel = 'Editor Sertifikat';

    protected static ?string $slug = 'editor-sertifikat';

    #[Url]
    public string $type = 'participation';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    public function mount(): void
    {
        if (! array_key_exists($this->type, Certificate::TYPES)) {
            $this->type = 'participation';
        }
    }

    public function getSubheading(): ?string
    {
        return 'Geser elemen di atas desain, atur gayanya di panel kanan, lalu Simpan. Tampilan di sini perkiraan; periksa hasil akhirnya dengan Pratinjau PDF.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label('Pratinjau PDF')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn (): string => route('admin.certificates.preview', $this->type), shouldOpenInNewTab: true),
            Action::make('settings')
                ->label('Gambar latar & huruf')
                ->icon('heroicon-o-photo')
                ->color('gray')
                ->url(fn (): string => ManageSiteSettings::getUrl(['tab' => 'sertifikat::data::tab'])),
        ];
    }

    /** Menyimpan tata letak satu jenis sertifikat dari editor. */
    public function saveLayout(array $layout): void
    {
        abort_unless(static::canAccess(), 403);

        $settings = app(SiteSettings::class);
        $all = (array) $settings->certificate_layout;
        $all[$this->type] = CertificatePdf::sanitizeLayout($layout);
        $settings->certificate_layout = $all;
        $settings->save();

        Notification::make()->title('Tata letak '.Certificate::TYPES[$this->type].' tersimpan')->success()->send();
    }

    public function enableCustomLayout(): void
    {
        abort_unless(static::canAccess(), 403);

        $settings = app(SiteSettings::class);
        $settings->certificate_custom_layout = true;
        $settings->save();

        Notification::make()->title('Template custom diaktifkan')->success()->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $settings = app(SiteSettings::class);
        $pdf = app(CertificatePdf::class);
        $background = $this->type === 'participation'
            ? ($settings->certificate_background_participation ?: $settings->certificate_background_presentation)
            : $settings->certificate_background_presentation;
        $font = $pdf->customFont();

        return [
            'customLayout' => (bool) $settings->certificate_custom_layout,
            'config' => [
                'type' => $this->type,
                'layout' => $pdf->layout($this->type),
                'background' => $this->publicUrl($background),
                'font' => $font ? ['family' => $font['family'], 'url' => route('admin.certificates.font')] : null,
                'labels' => collect(CertificatePdf::ELEMENTS)
                    ->reject(fn (string $label, string $element) => $element === 'paper_title' && $this->type !== 'presentation')
                    ->all(),
                'fonts' => CertificatePdf::FONTS,
                'sample' => [
                    'name' => 'Dr. Nama Penerima, S.E., M.M.',
                    'affiliation' => 'Universitas Contoh, Indonesia',
                    'paper_title' => 'Contoh Judul Paper yang Cukup Panjang untuk Menguji Pemenggalan Baris pada Sertifikat',
                    'number' => $this->type === 'presentation' ? 'ICOMAN2026-001/P1' : 'ICOMAN2026/A/0001',
                ],
            ],
        ];
    }

    /** Path relatif, supaya gambar tetap tampil walau APP_URL berbeda dari alamat yang dibuka. */
    private function publicUrl(?string $path): ?string
    {
        if (blank($path) || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return '/'.ltrim((string) parse_url(Storage::disk('public')->url($path), PHP_URL_PATH), '/');
    }
}
