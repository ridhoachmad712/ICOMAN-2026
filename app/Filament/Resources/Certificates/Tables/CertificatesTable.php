<?php

namespace App\Filament\Resources\Certificates\Tables;

use App\Models\Certificate;
use App\Services\CertificateIssuer;
use App\Services\CertificateZipExport;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class CertificatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('number')
            ->columns([
                TextColumn::make('number')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('recipient_name')
                    ->label('Penerima')
                    ->description(fn (Certificate $record): ?string => $record->recipient_affiliation)
                    ->searchable(['recipient_name', 'recipient_affiliation'])
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'presentation' ? 'Presenter' : 'Peserta seminar')
                    ->color(fn (string $state): string => $state === 'presentation' ? 'primary' : 'gray')
                    ->description(fn (Certificate $record): ?string => $record->isManual() ? 'Manual' : null),
                TextColumn::make('paper_title')
                    ->label('Judul paper')
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (Certificate $record): ?string => $record->paper_title)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('author.email')
                    ->label('Akun pengunduh')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('issued_at')
                    ->label('Terbit')
                    ->date('d M Y')
                    ->description(fn (Certificate $record): string => $record->issued_at->format('H:i'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (Certificate $record): string => $record->isRevoked() ? 'Dicabut' : 'Sah')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Sah' ? 'success' : 'danger')
                    ->description(fn (Certificate $record): ?string => $record->revoke_reason),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis')
                    ->options(['presentation' => 'Presenter', 'participation' => 'Peserta seminar']),
            ])
            ->recordActions([
                Action::make('pdf')
                    ->label('PDF')
                    ->tooltip('Unduh PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->button()
                    ->size('sm')
                    ->url(fn (Certificate $record): string => route('admin.certificates.pdf', $record))
                    ->openUrlInNewTab(),
                ActionGroup::make([
                    Action::make('verify')
                        ->label('Halaman verifikasi')
                        ->icon('heroicon-o-qr-code')
                        ->color('gray')
                        ->url(fn (Certificate $record): string => $record->verificationUrl())
                        ->openUrlInNewTab(),
                    // Sertifikat manual tidak punya sumber data untuk diambil ulang,
                    // jadi koreksinya langsung di sini (nomor dan QR tetap).
                    Action::make('editManual')
                        ->label('Ubah data')
                        ->icon('heroicon-o-pencil')
                        ->color('gray')
                        ->visible(fn (Certificate $record): bool => $record->isManual())
                        ->fillForm(fn (Certificate $record): array => $record->only(['recipient_name', 'recipient_affiliation', 'paper_title']))
                        ->schema(fn (Certificate $record): array => array_filter([
                            TextInput::make('recipient_name')->label('Nama penerima')->required()->maxLength(255),
                            TextInput::make('recipient_affiliation')->label('Afiliasi')->maxLength(255),
                            $record->type === 'presentation'
                                ? Textarea::make('paper_title')->label('Judul artikel')->rows(2)->required()
                                : null,
                        ]))
                        ->action(function (Certificate $record, array $data): void {
                            $record->update($data);
                            Notification::make()->title('Data sertifikat diperbarui')->success()->send();
                        }),
                    Action::make('revoke')
                        ->label('Cabut')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(fn (Certificate $record): bool => ! $record->isRevoked())
                        ->modalHeading('Cabut sertifikat')
                        ->modalDescription('Halaman verifikasi akan menyatakan sertifikat ini dicabut, dan peserta tidak bisa lagi mengunduhnya.')
                        ->schema([
                            TextInput::make('reason')->label('Alasan')->required()->maxLength(255),
                        ])
                        ->action(function (Certificate $record, array $data): void {
                            app(CertificateIssuer::class)->revoke($record, $data['reason']);
                            Notification::make()->title('Sertifikat dicabut')->success()->send();
                        }),
                    Action::make('reissue')
                        ->label('Terbitkan ulang')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('Nama, afiliasi, dan judul diambil ulang dari data terkini (perbaiki dulu bila ada salah tulis). Nomor tetap, tetapi kode QR diganti: cetakan lama tidak akan lolos verifikasi lagi.')
                        ->action(function (Certificate $record): void {
                            app(CertificateIssuer::class)->reissue($record, auth()->user());
                            Notification::make()->title('Sertifikat diterbitkan ulang')->success()->send();
                        }),
                ])
                    ->label('Lainnya')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->tooltip('Lainnya'),
            ])
            ->toolbarActions([
                BulkAction::make('downloadZip')
                    ->label('Unduh PDF terpilih (ZIP)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records, $livewire): void {
                        app(CertificateZipExport::class)->start(auth()->user(), currentEdition(), $records->modelKeys());
                        $livewire->dispatch('certificate-zip-started');

                        Notification::make()
                            ->title('ZIP sedang disiapkan')
                            ->body($records->count().' sertifikat. Tombol unduh muncul di atas tabel setelah selesai.')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateIcon('heroicon-o-check-badge')
            ->emptyStateHeading('Belum ada sertifikat')
            ->emptyStateDescription('Pilih "Terbitkan" setelah hari konferensi untuk menerbitkannya bagi semua yang memenuhi syarat.');
    }
}
