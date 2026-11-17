<?php

namespace App\Filament\Resources\Certificates\Pages;

use App\Filament\Resources\Certificates\CertificateResource;
use App\Models\Certificate;
use App\Services\CertificateIssuer;
use App\Services\ManualCertificateImport;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ListCertificates extends ListRecords
{
    protected static string $resource = CertificateResource::class;

    public function getSubheading(): ?string
    {
        return 'Presenter: satu sertifikat per penulis paper, bila invoice lunas, full paper sudah masuk, dan moderator mencatatnya hadir (atau diwakili) di room presentasi. Peserta seminar: bila invoice lunas; presenter yang memenuhi syarat di atas juga menerima sertifikat peserta seminar ini, jadi memegang keduanya. Semuanya diunduh peserta dari dashboard portal.';
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn ($query) => ($edition = currentEdition())
                ? $query->where('edition_id', $edition->id)
                : $query->whereRaw('1 = 0'));
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->issueAction('presentation'),
            $this->issueAction('participation'),
            ActionGroup::make([
                $this->manualAction(),
                $this->importAction(),
            ])
                ->label('Sertifikat manual')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->button()
                ->visible(fn (): bool => currentEdition() !== null),
            ActionGroup::make([
                $this->revokeAllAction(),
                $this->reissueRevokedAction(),
            ])
                ->label('Tarik sertifikat')
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('gray')
                ->button()
                ->visible(fn (): bool => currentEdition() !== null),
        ];
    }

    /** Jenis yang dipilih di formulir: null berarti kedua jenis. */
    private const SCOPES = [
        'all' => 'Semua sertifikat',
        'presentation' => 'Sertifikat presenter saja',
        'participation' => 'Sertifikat peserta seminar saja',
    ];

    private static function scopedType(array $data): ?string
    {
        return ($data['scope'] ?? 'all') === 'all' ? null : $data['scope'];
    }

    /**
     * Penarikan massal, mis. bila template atau data ternyata salah setelah
     * terbit. Diketik ulang kata konfirmasinya karena dampaknya ke semua orang.
     */
    private function revokeAllAction(): Action
    {
        return Action::make('revokeAll')
            ->label('Cabut semua sertifikat')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->modalHeading('Cabut semua sertifikat yang sudah terbit')
            ->modalDescription(function (): string {
                $valid = Certificate::where('edition_id', currentEdition()?->id)->valid()->count();

                return $valid.' sertifikat masih berlaku. Setelah dicabut, peserta tidak bisa mengunduhnya dan QR code-nya tampil "dicabut". '
                    .'Nomornya tetap tercatat: gunakan "Terbitkan ulang yang dicabut" untuk memulihkannya dengan data terkini.';
            })
            ->schema([
                Radio::make('scope')
                    ->label('Yang dicabut')
                    ->options(self::SCOPES)
                    ->default('all')
                    ->required(),
                TextInput::make('reason')
                    ->label('Alasan')
                    ->helperText('Tampil di halaman verifikasi dan di tab "Dicabut".')
                    ->required()
                    ->maxLength(255),
                TextInput::make('confirmation')
                    ->label('Ketik CABUT untuk melanjutkan')
                    ->required()
                    ->in(['CABUT'])
                    ->validationMessages(['in' => 'Ketik CABUT (huruf besar) untuk melanjutkan.']),
            ])
            ->modalSubmitActionLabel('Cabut')
            ->action(function (array $data): void {
                $count = app(CertificateIssuer::class)->revokeAll(currentEdition(), $data['reason'], self::scopedType($data));

                Notification::make()
                    ->title($count > 0 ? $count.' sertifikat dicabut' : 'Tidak ada sertifikat yang dicabut')
                    ->body($count > 0 ? 'Lihat tab "Dicabut". Penerbitan ulang memakai nomor yang sama.' : 'Tidak ada sertifikat berlaku untuk pilihan itu.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Sertifikat yang diisi tangan untuk penerima di luar alur otomatis. Tidak
     * terkait akun, jadi panitia mengunduh PDF-nya lalu mengirimkannya sendiri.
     */
    private function manualAction(): Action
    {
        return Action::make('issueManual')
            ->label('Buat sertifikat manual')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->modalHeading('Buat sertifikat manual')
            ->modalDescription('Untuk penerima di luar penerbitan otomatis. Sertifikat ini tidak muncul di dashboard peserta: unduh PDF-nya dari tabel lalu kirimkan sendiri. Keasliannya tetap bisa diperiksa lewat QR code.')
            ->schema([
                Radio::make('type')
                    ->label('Jenis')
                    ->options(['presentation' => 'Presenter', 'participation' => 'Peserta seminar'])
                    ->default('presentation')
                    ->inline()
                    ->live()
                    ->required(),
                TextInput::make('recipient_name')
                    ->label('Nama penerima')
                    ->helperText('Tulis persis seperti yang dicetak, termasuk gelar.')
                    ->required()
                    ->maxLength(255),
                TextInput::make('recipient_affiliation')
                    ->label('Afiliasi')
                    ->maxLength(255),
                Textarea::make('paper_title')
                    ->label('Judul artikel')
                    ->rows(2)
                    ->required(fn ($get): bool => $get('type') === 'presentation')
                    ->visible(fn ($get): bool => $get('type') === 'presentation'),
                TextInput::make('number')
                    ->label('Nomor sertifikat')
                    ->placeholder('Kosongkan untuk nomor otomatis')
                    ->helperText('Otomatis: '.$this->sequenceExample().'/P/0001 untuk presenter, melanjutkan urutan /A/ untuk peserta seminar.')
                    ->maxLength(255)
                    ->unique(Certificate::class, 'number'),
            ])
            ->modalSubmitActionLabel('Terbitkan')
            ->visible(fn (): bool => currentEdition() !== null)
            ->action(function (array $data): void {
                $certificate = app(CertificateIssuer::class)->issueManual(currentEdition(), $data, auth()->user());

                Notification::make()
                    ->title('Sertifikat '.$certificate->number.' diterbitkan')
                    ->body('Unduh PDF-nya dari tabel untuk dikirimkan ke '.$certificate->recipient_name.'.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Impor massal dari CSV. Semua baris diperiksa dulu: satu saja salah,
     * tidak ada yang diterbitkan, dan kesalahannya ditampilkan per baris.
     */
    private function importAction(): Action
    {
        return Action::make('importManual')
            ->label('Impor dari CSV')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->modalHeading('Impor sertifikat manual')
            ->modalDescription(new HtmlString(
                'Satu baris satu sertifikat, dengan kolom <b>jenis</b> (presenter / peserta), <b>nama</b>, <b>afiliasi</b>, <b>judul</b> (wajib untuk presenter), dan <b>nomor</b> (kosongkan untuk nomor otomatis). '
                .'Bila ada satu baris yang salah, tidak ada yang diterbitkan. '
                .'<a href="'.e(route('admin.certificates.import-template')).'" style="text-decoration:underline;font-weight:600">Unduh templat CSV</a>'
            ))
            ->schema([
                FileUpload::make('file')
                    ->label('Berkas CSV')
                    ->helperText('Dari Excel: File → Save As → CSV UTF-8. Pemisah koma atau titik koma sama-sama diterima.')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                    ->rules(['extensions:csv,txt'])
                    ->maxSize(2048)
                    ->storeFiles(false)
                    ->required(),
            ])
            ->modalSubmitActionLabel('Impor')
            ->action(function (array $data, Action $action): void {
                $result = app(ManualCertificateImport::class)->import(currentEdition(), $data['file']->getRealPath(), auth()->user());

                if ($result['errors'] !== []) {
                    $shown = array_slice($result['errors'], 0, 10);
                    $more = count($result['errors']) - count($shown);

                    Notification::make()
                        ->title('Impor dibatalkan: '.count($result['errors']).' kesalahan')
                        ->body(new HtmlString(
                            collect($shown)->map(fn (string $error) => e($error))->implode('<br>')
                            .($more > 0 ? '<br>…dan '.$more.' lainnya.' : '')
                            .'<br><br>Perbaiki berkasnya lalu unggah ulang; belum ada yang diterbitkan.'
                        ))
                        ->danger()
                        ->persistent()
                        ->send();
                    $action->halt();
                }

                Notification::make()
                    ->title($result['count'].' sertifikat manual diterbitkan')
                    ->body('Unduh PDF-nya dari tabel untuk dikirimkan ke masing-masing penerima.')
                    ->success()
                    ->send();
            });
    }

    private function sequenceExample(): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) currentEdition()?->name));
    }

    private function reissueRevokedAction(): Action
    {
        return Action::make('reissueRevoked')
            ->label('Terbitkan ulang yang dicabut')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->modalHeading('Terbitkan ulang sertifikat yang dicabut')
            ->modalDescription('Nama, afiliasi, dan judul diambil ulang dari data terkini, nomor tetap, dan kode QR diganti sehingga cetakan lama tidak lolos verifikasi.')
            ->schema([
                Radio::make('scope')
                    ->label('Yang diterbitkan ulang')
                    ->options(self::SCOPES)
                    ->default('all')
                    ->required(),
            ])
            ->modalSubmitActionLabel('Terbitkan ulang')
            ->action(function (array $data): void {
                $count = app(CertificateIssuer::class)->reissueRevoked(currentEdition(), auth()->user(), self::scopedType($data));

                Notification::make()
                    ->title($count > 0 ? $count.' sertifikat diterbitkan ulang' : 'Tidak ada sertifikat yang dicabut')
                    ->success()
                    ->send();
            });
    }

    public function getTabs(): array
    {
        $count = fn ($query) => ($edition = currentEdition()) ? $query->where('edition_id', $edition->id)->count() : 0;

        return [
            'all' => Tab::make('Semua')
                ->badge($count(Certificate::query()))
                ->badgeColor('gray'),
            'presentation' => Tab::make('Presenter')
                ->badge($count(Certificate::where('type', 'presentation')))
                ->modifyQueryUsing(fn ($query) => $query->where('type', 'presentation')),
            'participation' => Tab::make('Peserta seminar')
                ->badge($count(Certificate::where('type', 'participation')))
                ->modifyQueryUsing(fn ($query) => $query->where('type', 'participation')),
            'revoked' => Tab::make('Dicabut')
                ->badge($count(Certificate::whereNotNull('revoked_at')))
                ->badgeColor('danger')
                ->modifyQueryUsing(fn ($query) => $query->whereNotNull('revoked_at')),
        ];
    }

    /**
     * Penerbitan per jenis: sertifikat presenter baru lengkap setelah semua
     * moderator mengirim penilaian. Peserta seminar cukup lunas, tetapi
     * presenter ikut menerima sertifikat peserta bila paper-nya dipresentasikan.
     */
    private function issueAction(string $type): Action
    {
        $presenter = $type === 'presentation';
        $label = $presenter ? 'presenter' : 'peserta seminar';

        return Action::make($presenter ? 'issuePresentation' : 'issueParticipation')
            ->label('Terbitkan sertifikat '.$label)
            ->icon($presenter ? 'heroicon-o-presentation-chart-bar' : 'heroicon-o-user-group')
            ->color($presenter ? 'primary' : 'gray')
            ->requiresConfirmation()
            ->modalHeading('Terbitkan sertifikat '.$label)
            ->modalDescription(function () use ($type, $label, $presenter): string {
                $edition = currentEdition();
                if (! $edition) {
                    return 'Belum ada edisi aktif.';
                }

                $count = app(CertificateIssuer::class)->pendingCounts($edition)[$type];

                if ($count === 0) {
                    return 'Semua '.$label.' yang memenuhi syarat sudah memiliki sertifikat; tidak ada sertifikat baru.';
                }

                return 'Akan diterbitkan '.$count.' sertifikat '.$label.' baru'.($presenter ? ' (satu per penulis paper)' : '').'. '
                    .'Pastikan semua moderator sudah mengirim penilaian, karena kehadiran menjadi syarat'
                    .($presenter ? '.' : ' bagi presenter, yang ikut menerima sertifikat ini.');
            })
            // Yang dicabut tidak terbit lagi diam-diam: panitia memilih di sini.
            ->schema(fn (): array => ($revoked = $this->revokedCount($type)) > 0 ? [
                Toggle::make('include_revoked')
                    ->label('Terbitkan ulang juga '.$revoked.' sertifikat '.$label.' yang dicabut')
                    ->helperText('Nomor tetap; nama, afiliasi, dan judul diambil dari data terkini, dan QR lama tidak berlaku lagi. Matikan bila ada yang sengaja dicabut (mis. salah penerima).')
                    ->default(true),
            ] : [])
            ->modalSubmitActionLabel('Terbitkan')
            ->visible(fn (): bool => currentEdition() !== null)
            ->action(function (array $data) use ($type, $label): void {
                $issuer = app(CertificateIssuer::class);
                $count = $issuer->issuePending(currentEdition(), auth()->user(), $type);
                $restored = ($data['include_revoked'] ?? false)
                    ? $issuer->reissueRevoked(currentEdition(), auth()->user(), $type)
                    : 0;

                $parts = array_filter([
                    $count > 0 ? $count.' sertifikat '.$label.' diterbitkan' : null,
                    $restored > 0 ? $restored.' diterbitkan ulang' : null,
                ]);

                Notification::make()
                    ->title($parts ? implode(', ', $parts) : 'Tidak ada sertifikat '.$label.' baru')
                    ->body($parts ? 'Penerima sudah dapat mengunduhnya dari dashboard portal.' : 'Semua yang memenuhi syarat sudah memiliki sertifikat.')
                    ->success()
                    ->send();
            });
    }

    private function revokedCount(?string $type): int
    {
        return Certificate::query()
            ->where('edition_id', currentEdition()?->id)
            ->when($type, fn ($query) => $query->where('type', $type))
            ->whereNotNull('revoked_at')
            ->count();
    }
}
