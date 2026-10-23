<?php

namespace App\Filament\Resources\Certificates\Tables;

use App\Models\Certificate;
use App\Services\CertificateIssuer;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                    ->color(fn (string $state): string => $state === 'presentation' ? 'primary' : 'gray'),
                TextColumn::make('paper_title')
                    ->label('Judul paper')
                    ->limit(50)
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('author.email')
                    ->label('Akun pengunduh')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('issued_at')
                    ->label('Terbit')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (Certificate $record): string => $record->isRevoked() ? 'Dicabut' : 'Sah')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Sah' ? 'success' : 'danger')
                    ->description(fn (Certificate $record): ?string => $record->revoke_reason),
            ])
            ->recordActions([
                Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn (Certificate $record): string => route('admin.certificates.pdf', $record))
                    ->openUrlInNewTab(),
                Action::make('verify')
                    ->label('Halaman verifikasi')
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->url(fn (Certificate $record): string => $record->verificationUrl())
                    ->openUrlInNewTab(),
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
            ->emptyStateIcon('heroicon-o-check-badge')
            ->emptyStateHeading('Belum ada sertifikat')
            ->emptyStateDescription('Tekan "Terbitkan sertifikat" setelah hari konferensi untuk menerbitkannya bagi semua yang memenuhi syarat.');
    }
}
