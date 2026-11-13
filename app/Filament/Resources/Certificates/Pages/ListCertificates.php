<?php

namespace App\Filament\Resources\Certificates\Pages;

use App\Filament\Resources\Certificates\CertificateResource;
use App\Models\Certificate;
use App\Services\CertificateIssuer;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;

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
