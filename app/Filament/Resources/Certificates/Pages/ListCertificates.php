<?php

namespace App\Filament\Resources\Certificates\Pages;

use App\Filament\Resources\Certificates\CertificateResource;
use App\Models\Certificate;
use App\Services\CertificateIssuer;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;

class ListCertificates extends ListRecords
{
    protected static string $resource = CertificateResource::class;

    public function getSubheading(): ?string
    {
        return 'Presenter: satu sertifikat per penulis paper, bila invoice lunas, full paper sudah masuk, dan moderator mencatatnya hadir (atau diwakili) di room presentasi. Peserta seminar: bila invoice lunas. Semuanya diunduh peserta dari dashboard portal.';
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
        ];
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
     * moderator mengirim penilaian, sedangkan peserta seminar cukup lunas.
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
                    return 'Semua '.$label.' yang memenuhi syarat sudah memiliki sertifikat. Tidak ada yang akan diterbitkan.';
                }

                return 'Akan diterbitkan '.$count.' sertifikat '.$label.($presenter ? ' (satu per penulis paper)' : '').'. '
                    .($presenter ? 'Pastikan semua moderator sudah mengirim penilaian, karena kehadiran menjadi syarat. ' : '')
                    .'Yang sudah punya sertifikat, termasuk yang pernah dicabut, tidak diterbitkan lagi.';
            })
            ->modalSubmitActionLabel('Terbitkan')
            ->visible(fn (): bool => currentEdition() !== null)
            ->action(function () use ($type, $label): void {
                $count = app(CertificateIssuer::class)->issuePending(currentEdition(), auth()->user(), $type);

                Notification::make()
                    ->title($count > 0 ? $count.' sertifikat '.$label.' diterbitkan' : 'Tidak ada sertifikat '.$label.' baru')
                    ->body($count > 0 ? 'Penerima sudah dapat mengunduhnya dari dashboard portal.' : 'Semua yang memenuhi syarat sudah memiliki sertifikat.')
                    ->success()
                    ->send();
            });
    }
}
