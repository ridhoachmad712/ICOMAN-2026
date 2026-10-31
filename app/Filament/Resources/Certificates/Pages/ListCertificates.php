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
            Action::make('issue')
                ->label('Terbitkan sertifikat')
                ->icon('heroicon-o-check-badge')
                ->requiresConfirmation()
                ->modalHeading('Terbitkan sertifikat')
                ->modalDescription(function (): string {
                    $edition = currentEdition();
                    if (! $edition) {
                        return 'Belum ada edisi aktif.';
                    }

                    $counts = app(CertificateIssuer::class)->pendingCounts($edition);

                    return $counts['presentation'] + $counts['participation'] === 0
                        ? 'Semua yang memenuhi syarat sudah memiliki sertifikat. Tidak ada yang akan diterbitkan.'
                        : "Akan diterbitkan {$counts['presentation']} sertifikat presenter (per penulis) dan {$counts['participation']} sertifikat peserta seminar. "
                            .'Yang sudah punya sertifikat, termasuk yang pernah dicabut, tidak diterbitkan lagi.';
                })
                ->modalSubmitActionLabel('Terbitkan')
                ->visible(fn (): bool => currentEdition() !== null)
                ->action(function (): void {
                    $count = app(CertificateIssuer::class)->issuePending(currentEdition(), auth()->user());

                    Notification::make()
                        ->title($count > 0 ? $count.' sertifikat diterbitkan' : 'Tidak ada sertifikat baru')
                        ->body($count > 0 ? 'Peserta sudah dapat mengunduhnya dari dashboard portal.' : 'Semua yang memenuhi syarat sudah memiliki sertifikat.')
                        ->success()
                        ->send();
                }),
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
}
