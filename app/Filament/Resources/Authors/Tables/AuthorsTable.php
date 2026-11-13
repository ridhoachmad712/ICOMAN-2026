<?php

namespace App\Filament\Resources\Authors\Tables;

use App\Filament\Author\Pages\AuthorDashboard;
use App\Filament\Resources\Registrations\RegistrationResource;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Models\Author;
use App\Models\Registration;
use App\Models\Submission;
use App\Services\AuthorProgress;
use App\Services\ParticipantContactExport;
use App\Services\RegistrationProvisioner;
use App\Services\SpySession;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class AuthorsTable
{
    /** Jalur partisipasi beserta warna badge-nya. */
    private const PATHS = [
        'presenter' => ['Presenter', 'primary'],
        'participant' => ['Peserta seminar', 'gray'],
        'cohost' => ['Co-host', 'warning'],
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            // Tetap mulai dari tabel authors, bukan submissions: akun yang belum
            // mengirim paper pun harus muncul. Relasi dibatasi edisi aktif dan
            // dimuat bersama agar tabel tidak membuat query untuk tiap baris.
            ->modifyQueryUsing(function (Builder $query): Builder {
                $editionId = currentEdition()?->id ?? -1;

                return $query->with([
                    'submissions' => fn ($papers) => $papers->where('edition_id', $editionId)->with(['reviewAssignments', 'media']),
                    'registrations' => fn ($registrations) => $registrations->where('edition_id', $editionId),
                    'coHost' => fn ($coHost) => $coHost->where('edition_id', $editionId),
                ]);
            })
            ->columns([
                // Nama dan email selalu dibaca bersama, jadi menempati satu kolom
                // saja — barisnya jauh lebih ringkas tanpa kehilangan apa pun.
                TextColumn::make('name')
                    ->label('Peserta')
                    ->searchable(['name', 'email'])
                    ->sortable()
                    ->wrap()
                    ->weight('medium')
                    ->description(fn (Author $record): string => $record->email)
                    ->copyable()
                    ->copyableState(fn (Author $record): string => $record->email),

                TextColumn::make('participation_type')
                    ->label('Jalur')
                    ->badge()
                    ->state(fn (Author $record): string => self::PATHS[$record->participation_type][0] ?? 'Belum dipilih')
                    ->color(fn (Author $record): string => self::PATHS[$record->participation_type][1] ?? 'gray')
                    // Kategori menentukan tarif, jadi ditempel di bawah jalurnya.
                    // Untuk co-host kategori tidak berlaku: yang membayar institusi.
                    ->description(fn (Author $record): ?string => $record->isCoHost()
                        ? null
                        : $record->registrantCategoryLabel()),

                TextColumn::make('progress')
                    ->label('Progres saat ini')
                    ->badge()
                    ->state(fn (Author $record): string => self::progress($record)['label'])
                    ->color(fn (Author $record): string => self::progress($record)['color'])
                    ->description(fn (Author $record): string => self::progress($record)['detail'])
                    ->wrap(),

                TextColumn::make('paper')
                    ->label('Paper')
                    ->state(fn (Author $record): string => self::progress($record)['paper']?->submission_number ?? 'Belum ada')
                    ->description(fn (Author $record): ?string => ($paper = self::progress($record)['paper'])
                        ? (Submission::STATUS_LABELS[$paper->status] ?? $paper->status)
                        : null)
                    ->color('gray'),

                TextColumn::make('payment')
                    ->label('Pembayaran')
                    ->badge()
                    ->state(fn (Author $record): string => self::paymentLabel(self::progress($record)['registration']))
                    ->color(fn (Author $record): string => self::paymentColor(self::progress($record)['registration']))
                    ->description(fn (Author $record): ?string => ($registration = self::progress($record)['registration'])
                        ? 'Invoice #'.str_pad((string) $registration->id, 5, '0', STR_PAD_LEFT)
                        : null),

                TextColumn::make('affiliation')
                    ->label('Afiliasi')
                    ->limit(40)
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('phone')
                    ->label('Telepon')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),

                TextColumn::make('created_at')->label('Terdaftar')->dateTime('d M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('participation_type')
                    ->label('Jalur')
                    ->options(fn (): array => collect(self::PATHS)->map(fn (array $path): string => $path[0])->all()),
                SelectFilter::make('registrant_category')->label('Kategori')->options(Author::CATEGORIES),
            ])
            ->recordActions([
                Action::make('viewPaper')
                    ->label('Lihat paper')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->url(fn (Author $record): ?string => ($paper = self::progress($record)['paper'])
                        ? SubmissionResource::getUrl('edit', ['record' => $paper], panel: 'admin')
                        : null)
                    ->visible(fn (Author $record): bool => self::progress($record)['paper'] !== null),

                Action::make('viewInvoice')
                    ->label('Lihat invoice')
                    ->icon('heroicon-o-banknotes')
                    ->color('gray')
                    ->url(fn (Author $record): ?string => ($registration = self::progress($record)['registration'])
                        ? RegistrationResource::getUrl('edit', ['record' => $registration], panel: 'admin')
                        : null)
                    ->visible(fn (Author $record): bool => self::progress($record)['registration'] !== null),

                // Invoice biasanya lahir saat author lanjut ke pembayaran. Selama
                // pembayaran ditutup itu tidak bisa lagi, padahal "Tandai Lunas"
                // membutuhkan invoice untuk peserta yang transfer di luar portal.
                Action::make('createInvoice')
                    ->label('Buat invoice')
                    ->icon('heroicon-o-document-plus')
                    ->color('warning')
                    ->visible(fn (Author $record): bool => self::canReceiveInvoice($record))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Author $record): string => 'Buat invoice untuk '.$record->name.'?')
                    ->modalDescription('Tarif mengikuti kategori peserta. Invoice ini belum lunas: tandai lunas di menu Registrasi setelah pembayarannya dipastikan.')
                    ->modalSubmitActionLabel('Buat invoice')
                    ->action(function (Author $record, Action $action): void {
                        $registration = app(RegistrationProvisioner::class)->ensureFor($record, byCommittee: true);

                        if (! $registration) {
                            Notification::make()
                                ->title('Invoice tidak dapat dibuat')
                                ->body('Tarif untuk kategori peserta ini belum tersedia. Periksa menu Tarif Registrasi.')
                                ->danger()
                                ->send();

                            $action->halt();
                        }

                        Notification::make()
                            ->title('Invoice #'.str_pad((string) $registration->id, 5, '0', STR_PAD_LEFT).' dibuat')
                            ->success()
                            ->send();
                    }),

                ActionGroup::make([
                    Action::make('viewAsAuthor')
                        ->label('Lihat sebagai peserta')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->visible(fn (): bool => SpySession::canStart(auth()->user()))
                        ->requiresConfirmation()
                        ->modalHeading(fn (Author $record): string => 'Lihat akun '.$record->name.'?')
                        ->modalDescription('Anda masuk ke portal seperti yang peserta lihat, tanpa bisa mengubah data. Kegiatan ini dicatat di log aplikasi.')
                        ->modalSubmitActionLabel('Lihat akun')
                        ->action(function (Author $record, Action $action): void {
                            app(SpySession::class)->start(auth()->user(), $record);

                            $action->redirect(AuthorDashboard::getUrl(panel: 'author'));
                        }),

                    // Setelah sertifikat terbit, peserta tidak bisa lagi mengubah
                    // nama dan afiliasinya sendiri; koreksinya lewat sini.
                    Action::make('editIdentity')
                        ->label('Ubah nama & afiliasi')
                        ->icon('heroicon-o-pencil-square')
                        ->color('gray')
                        ->modalHeading(fn (Author $record): string => 'Ubah nama & afiliasi '.$record->name)
                        ->modalDescription(fn (Author $record): string => $record->certificates()->exists()
                            ? 'Sertifikat yang sudah terbit tidak ikut berubah. Setelah menyimpan, buka menu Sertifikat dan klik "Terbitkan ulang" pada sertifikat akun ini. Nama penulis di sertifikat presenter diambil dari daftar author paper, bukan dari sini.'
                            : 'Nama dan afiliasi akun peserta. Nama penulis di sertifikat presenter diambil dari daftar author paper, bukan dari sini.')
                        ->fillForm(fn (Author $record): array => ['name' => $record->name, 'affiliation' => $record->affiliation])
                        ->schema([
                            TextInput::make('name')->label('Nama lengkap (dengan gelar)')->required()->maxLength(255),
                            TextInput::make('affiliation')->label('Afiliasi')->maxLength(255),
                        ])
                        ->action(function (array $data, Author $record): void {
                            abort_unless(auth()->user()?->managesSubmissions(), 403);
                            $record->update(['name' => $data['name'], 'affiliation' => $data['affiliation'] ?? null]);

                            Notification::make()
                                ->title('Nama dan afiliasi diperbarui')
                                ->body($record->certificates()->exists() ? 'Jangan lupa klik "Terbitkan ulang" di menu Sertifikat.' : null)
                                ->success()
                                ->send();
                        }),

                    // Portal author tidak punya reset mandiri, jadi ini satu-satunya
                    // jalan memulihkan akun yang lupa password.
                    Action::make('resetPassword')
                        ->label('Reset password')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->visible(fn (): bool => auth()->user()?->isSuperadmin() ?? false)
                        ->modalHeading(fn (Author $record) => 'Reset password untuk '.$record->name)
                        ->modalDescription('Password lama langsung tidak berlaku. Sampaikan password baru ini ke peserta melalui kanal resmi.')
                        ->schema([
                            TextInput::make('password')
                                ->label('Password baru')
                                ->password()
                                ->revealable()
                                ->required()
                                ->minLength(8)
                                ->helperText('Minimal 8 karakter.'),
                        ])
                        ->action(function (array $data, Author $record): void {
                            abort_unless(auth()->user()?->isSuperadmin(), 403);
                            $record->forceFill(['password' => Hash::make($data['password'])])->save();

                            Notification::make()
                                ->title('Password '.$record->name.' berhasil direset.')
                                ->body('Sampaikan password baru ke peserta; mereka dapat langsung login.')
                                ->success()
                                ->persistent()
                                ->send();
                        }),

                    // Hapus permanen. Tanpa ini akun yang salah daftar terus
                    // menempati alamat emailnya (kolom `email` unik), sehingga
                    // peserta tidak bisa mendaftar ulang dengan email yang sama.
                    DeleteAction::make()
                        ->label('Hapus permanen')
                        ->visible(fn (): bool => auth()->user()?->isSuperadmin() ?? false)
                        ->modalHeading(fn (Author $record) => 'Hapus permanen akun '.$record->name.'?')
                        ->modalDescription(fn (Author $record) => static::deletionWarning($record))
                        ->modalSubmitActionLabel('Hapus permanen')
                        ->before(fn (Author $record) => static::purgeRelated($record)),
                ])->label('Kelola')->visible(fn (): bool => SpySession::canStart(auth()->user())),
            ])
            ->headerActions([
                Action::make('exportContacts')
                    ->label('Unduh Data Peserta')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->tooltip('Nama, afiliasi, email, dan nomor HP dalam Excel. Mengikuti tab, filter, dan pencarian yang sedang aktif.')
                    // Diambil dari query tabel yang sudah tersaring, jadi berkasnya
                    // berisi persis daftar yang sedang dilihat panitia.
                    ->action(function (HasTable $livewire, ParticipantContactExport $export) {
                        return response()
                            ->download($export->create($livewire->getFilteredSortedTableQuery()), $export->fileName())
                            ->deleteFileAfterSend();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus permanen')
                        ->before(fn ($records) => $records->each(fn (Author $author) => static::purgeRelated($author))),
                ])->visible(fn (): bool => auth()->user()?->isSuperadmin() ?? false),
            ])
            ->emptyStateIcon('heroicon-o-users')
            ->emptyStateHeading('Belum ada akun author')
            ->emptyStateDescription('Akun akan muncul di sini setelah peserta mendaftar melalui portal.');
    }

    /** @return array{label: string, detail: string, color: string, percent: int, paper: ?Submission, registration: ?Registration} */
    private static function progress(Author $author): array
    {
        return app(AuthorProgress::class)->snapshot($author);
    }

    /** Belum punya invoice, dan jalurnya sudah sampai ke tahap membayar. */
    private static function canReceiveInvoice(Author $author): bool
    {
        if (self::progress($author)['registration'] !== null) {
            return false;
        }

        if ($author->isParticipant()) {
            return true;
        }

        return $author->isPresenter() && $author->submissions
            ->contains(fn (Submission $paper): bool => $paper->status === 'accepted' && $paper->isLoaIssued());
    }

    private static function paymentLabel(?Registration $registration): string
    {
        return AuthorProgress::paymentLabel($registration);
    }

    private static function paymentColor(?Registration $registration): string
    {
        return match ($registration?->status) {
            'paid' => 'success',
            'pending_verification' => 'info',
            'failed' => 'danger',
            'pending' => 'warning',
            default => 'gray',
        };
    }

    /** Sebutkan apa saja yang ikut hilang, supaya tidak ada kejutan. */
    private static function deletionWarning(Author $author): string
    {
        $abstracts = $author->submissions()->count();
        $registrations = $author->registrations()->count();
        $paid = $author->registrations()->where('status', 'paid')->count();

        $lines = ['Baris database akun ini dihapus permanen, sehingga email '.$author->email.' bisa dipakai mendaftar lagi.'];

        if ($abstracts || $registrations) {
            $lines[] = 'Ikut terhapus: '.$abstracts.' abstrak (beserta penilaian reviewer) dan '.$registrations.' registrasi beserta riwayat pembayarannya.';
        }

        if ($paid) {
            $lines[] = 'PERHATIAN: '.$paid.' registrasi berstatus LUNAS. Catatan pembayarannya akan hilang — pastikan sudah dicatat di luar sistem sebelum melanjutkan.';
        }

        return implode(' ', $lines);
    }

    /**
     * Hapus turunan lewat Eloquent lebih dulu. Foreign key di database memang
     * cascade, tapi cascade tingkat database melewati event model sehingga
     * berkas media (abstrak, full paper, bukti bayar) akan tertinggal sebagai
     * sampah di storage.
     */
    private static function purgeRelated(Author $author): void
    {
        $author->submissions()->cursor()->each(fn ($submission) => $submission->delete());
        $author->registrations()->cursor()->each(fn ($registration) => $registration->delete());
    }
}
