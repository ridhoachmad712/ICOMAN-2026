<?php

namespace App\Filament\Resources\Authors\Tables;

use App\Filament\Resources\Registrations\RegistrationResource;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Models\Author;
use App\Models\Registration;
use App\Models\Submission;
use App\Services\AuthorProgress;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
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

                ActionGroup::make([
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
                ])->label('Kelola')->visible(fn (): bool => auth()->user()?->isSuperadmin() ?? false),
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

    private static function paymentLabel(?Registration $registration): string
    {
        return match ($registration?->status) {
            'paid' => 'Lunas',
            'pending_verification' => 'Verifikasi',
            'failed' => 'Gagal',
            'pending' => 'Belum lunas',
            default => 'Belum ada invoice',
        };
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
