<?php

namespace App\Filament\Resources\Submissions\Tables;

use App\Models\Review;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\LoaIssued;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

class SubmissionsTable
{
    private const STATUS_OPTIONS = [
        'extended_abstract_draft' => 'Draft Abstract',
        'extended_abstract_submitted' => 'Abstract Terkirim',
        'extended_abstract_under_review' => 'Sedang Direview',
        'revision_required' => 'Perlu Revisi',
        'accepted' => 'Accepted',
        'rejected' => 'Tidak Lolos',
    ];

    /**
     * Opsi untuk KOREKSI manual saja. Status lain sengaja tidak ditawarkan karena
     * sudah ditulis otomatis oleh alur kerja:
     * - Draft/Terkirim/Sedang Direview  -> otomatis dari aksi author & "Assign Reviewer".
     * - Accepted                        -> lewat "Keputusan Review", supaya LOA terbit
     *                                      dan email dikirim dengan konteks hasil review.
     */
    private const MANUAL_STATUS_OPTIONS = [
        'extended_abstract_submitted' => 'Kembalikan ke antrean review',
        'revision_required' => 'Minta revisi ke author',
        'rejected' => 'Tandai tidak lolos',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                // Kode submission kini cukup pendek untuk ditampilkan apa adanya,
                // jadi admin dan author memakai nomor yang sama persis.
                TextColumn::make('submission_number')
                    ->label('Kode')
                    ->copyable()
                    ->sortable()
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('submission_number', 'like', "%{$search}%")),
                TextColumn::make('author.name')->label('Submitter')->searchable()->toggleable(),
                // Judul disembunyikan agar tabel ringkas; bisa diaktifkan lewat
                // tombol pemilih kolom bila sewaktu-waktu dibutuhkan.
                TextColumn::make('title')
                    ->label('Judul')
                    ->limit(45)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reviewAssignments.reviewer.name')
                    ->label('Reviewer')
                    ->badge()
                    ->separator(',')
                    ->color('info')
                    ->placeholder('Belum di-assign'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Submission::STATUS_LABELS[$state] ?? ucwords(str_replace('_', ' ', $state)))
                    ->color(fn (string $state) => match ($state) {
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        'revision_required', 'extended_abstract_submitted' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('submitted_at')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::STATUS_OPTIONS),
                SelectFilter::make('edition_id')->relationship('edition', 'name')->label('Edition'),
            ])
            ->recordActions([
                // LANGKAH BERIKUTNYA — hanya satu yang tampil (kondisinya saling eksklusif),
                // sehingga admin selalu melihat tepat satu tombol aksi utama per baris.
                Action::make('assignReviewer')
                    // Satu paper dinilai satu reviewer, jadi tombolnya menyebut
                    // apa yang sebenarnya terjadi: menugaskan, atau menggantikan.
                    ->label(fn ($record): string => static::currentReviewer($record) ? 'Ganti Reviewer' : 'Assign Reviewer')
                    ->icon(fn ($record): string => static::currentReviewer($record) ? 'heroicon-o-arrow-path' : 'heroicon-o-user-plus')
                    ->color('info')
                    ->visible(fn ($record) => $record->currentReviewPhase() !== null
                        && ! $record->reviewAssignments()
                            ->where('phase', $record->currentReviewPhase())
                            ->where('status', 'completed')
                            ->exists())
                    ->modalHeading(fn ($record): string => static::currentReviewer($record) ? 'Ganti Reviewer' : 'Assign Reviewer')
                    ->modalSubmitActionLabel(fn ($record): string => static::currentReviewer($record) ? 'Ganti' : 'Tugaskan')
                    ->modalDescription(fn ($record) => static::assignDescription($record))
                    ->schema([
                        Toggle::make('show_all')
                            ->label('Tampilkan semua reviewer')
                            ->helperText('Abaikan kepakaran dan tampilkan seluruh reviewer.')
                            ->default(fn ($record): bool => static::expertReviewers($record)->isEmpty())
                            ->live()
                            ->dehydrated(false),

                        Select::make('reviewer_id')
                            ->label('Pilih Reviewer')
                            // Pilihan disaring ke kepakaran sub-tema paper ini;
                            // reviewer yang kepakarannya belum diisi tetap ikut,
                            // supaya penyaringan menyala bertahap.
                            ->options(fn ($get, $record) => static::reviewerOptions($record, (bool) $get('show_all')))
                            // Pilihan yang disaring hanya membatasi tampilan; tanpa ini
                            // kiriman lama atau yang dirakit sendiri masih bisa
                            // menugaskan reviewer di luar kepakarannya.
                            ->rules([
                                fn ($get, $record) => Rule::in(
                                    static::reviewerOptions($record, (bool) $get('show_all'))->keys()->all()
                                ),
                            ])
                            ->required()
                            ->helperText(fn ($get): string => $get('show_all')
                                ? 'Seluruh reviewer ditampilkan, termasuk yang kepakarannya berbeda.'
                                : 'Hanya reviewer dengan kepakaran pada sub-tema ini.'),
                    ])
                    ->fillForm(fn ($record) => [
                        'reviewer_id' => static::currentReviewer($record),
                    ])
                    ->action(function (array $data, $record): void {
                        $phase = $record->currentReviewPhase();
                        if (! $phase) {
                            return;
                        }

                        $selected = (int) $data['reviewer_id'];
                        $phaseAssignments = $record->reviewAssignments()->where('phase', $phase);
                        $previous = (clone $phaseAssignments)->value('reviewer_id');

                        // Satu paper satu reviewer: penugasan lama dilepas, bukan ditumpuk.
                        (clone $phaseAssignments)->where('reviewer_id', '!=', $selected)->delete();

                        $record->reviewAssignments()->firstOrCreate(
                            ['reviewer_id' => $selected, 'phase' => $phase],
                            ['assigned_at' => now(), 'status' => 'pending'],
                        );

                        $record->changeStatus('extended_abstract_under_review');

                        Notification::make()
                            ->title($previous && $previous !== $selected
                                ? 'Reviewer diganti & status diperbarui.'
                                : 'Reviewer berhasil ditugaskan & status diperbarui.')
                            ->success()
                            ->send();
                    }),

                Action::make('decision')
                    ->label('Keputusan Review')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->modalHeading('Keputusan Panitia atas Abstract')
                    // Menerima/menolak paper otomatis menerbitkan LOA dan mengirim
                    // email ke author, jadi wewenangnya sejajar dengan penerbitan LOA.
                    ->visible(fn ($record) => (auth()->user()?->isSuperadmin() ?? false)
                        && $record->currentReviewPhase() !== null
                        && $record->reviewAssignments()
                            ->where('phase', $record->currentReviewPhase())
                            ->where('status', 'completed')
                            ->exists()
                        && ! $record->reviewAssignments()
                            ->where('phase', $record->currentReviewPhase())
                            ->where('status', 'pending')
                            ->exists())
                    ->schema([
                        Placeholder::make('review_summary')
                            ->label('Hasil Penilaian Reviewer')
                            ->content(function ($record): HtmlString {
                                $completed = $record->reviewAssignments()
                                    ->where('phase', $record->currentReviewPhase())
                                    ->where('status', 'completed')
                                    ->with(['reviewer', 'review'])
                                    ->get();
                                if ($completed->isEmpty()) {
                                    return new HtmlString('<p class="text-xs text-gray-500">Belum ada review selesai.</p>');
                                }

                                $html = $completed->map(function ($ra) {
                                    $rev = $ra->review;
                                    $rec = $rev?->recommendation ? ucwords(str_replace('_', ' ', $rev->recommendation)) : '—';
                                    $score = $rev?->score ? $rev->score.'/100' : '—';
                                    $comments = $rev?->comments_for_author ? '<p class="mt-1 text-xs text-gray-600"><strong>Catatan Reviewer:</strong> '.e($rev->comments_for_author).'</p>' : '';

                                    // Rekomendasi SINTA 3 ikut disebut: keputusan Accept
                                    // di bawah inilah yang membuka tawarannya ke author.
                                    $sinta3 = $rev?->recommends_sinta3
                                        ? '<div class="mt-1 font-semibold">Direkomendasikan terbit di Jurnal SINTA 3</div>'
                                        : '';

                                    return '<div class="p-3 bg-gray-50 border border-gray-200 rounded-lg mb-2 text-xs">'
                                        .'<div class="font-bold text-gray-900">'.e($ra->reviewer?->name).'</div>'
                                        .'<div>Skor: <span class="font-semibold">'.$score.'</span> &bull; Rekomendasi: <span class="font-semibold text-primary-700">'.$rec.'</span></div>'
                                        .$sinta3
                                        .$comments
                                        .'</div>';
                                })->implode('');

                                return new HtmlString($html);
                            }),

                        Select::make('status')
                            ->label('Keputusan Panitia')
                            ->options([
                                'accepted' => 'Accepted (LOA otomatis terbit)',
                                'revision_required' => 'Minta Revisi (kembalikan ke author)',
                                'rejected' => 'Tidak Lolos',
                            ])
                            ->required()
                            ->helperText('Author menerima email resmi otomatis. Bila "Accepted", LOA langsung terbit; tawaran Jurnal SINTA 3 mengikuti rekomendasi reviewer.'),
                    ])
                    ->action(function (array $data, $record): void {
                        $record->changeStatus($data['status']);

                        Notification::make()->title('Keputusan disimpan. Bila Accepted, LOA otomatis terbit & email dikirim ke author.')->success()->send();
                    }),

                // Tawaran mengikuti rekomendasi reviewer dengan sendirinya; tombol ini
                // untuk saat panitia perlu memutuskan lain, dan keputusannya
                // tidak akan ditimpa penilaian berikutnya.
                Action::make('sinta3Offer')
                    ->label(fn ($record): string => $record->sinta3_offered ? 'Tutup tawaran SINTA 3' : 'Buka tawaran SINTA 3')
                    ->icon('heroicon-o-academic-cap')
                    ->color(fn ($record): string => $record->sinta3_offered ? 'gray' : 'info')
                    ->visible(fn ($record): bool => $record->status === 'accepted'
                        && (auth()->user()?->isSuperadmin() ?? false))
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record): string => $record->sinta3_offered
                        ? 'Tutup tawaran Jurnal SINTA 3?'
                        : 'Buka tawaran Jurnal SINTA 3?')
                    ->modalDescription(fn ($record): string => static::sinta3Description($record))
                    ->action(function ($record): void {
                        $record->setSinta3Offer(! $record->sinta3_offered);

                        Notification::make()
                            ->title($record->fresh()->sinta3_offered
                                ? 'Tawaran SINTA 3 dibuka. Author dapat memilihnya di halaman pembayaran.'
                                : 'Tawaran SINTA 3 ditutup.')
                            ->success()
                            ->send();
                    }),

                Action::make('issueLoa')
                    ->label('Terbitkan LOA')
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'accepted'
                        && ! $record->isLoaIssued()
                        && (auth()->user()?->isSuperadmin() ?? false))
                    ->modalHeading('Terbitkan Letter of Acceptance')
                    ->modalDescription('LOA otomatis terbit saat status diubah menjadi Accepted. Tombol ini hanya untuk paper yang sudah Accepted tetapi LOA-nya belum terbit (mis. diterima sebelum fitur otomatis aktif).')
                    ->schema([
                        Toggle::make('sinta3_offered')
                            ->label('Tawarkan penerbitan Jurnal SINTA 3 (biaya tambahan)')
                            ->helperText('Default mengikuti rekomendasi reviewer; masih bisa diubah di sini.')
                            ->default(fn ($record) => $record->reviewsRecommendSinta3()),
                    ])
                    ->action(function (array $data, $record): void {
                        $record->update([
                            'loa_issued_at' => now(),
                            'sinta3_offered' => (bool) ($data['sinta3_offered'] ?? false),
                        ]);

                        $record->author?->notify(new LoaIssued($record));

                        Notification::make()->title('LOA diterbitkan & tersedia di akun author.')->success()->send();
                    }),

                // Aksi pendukung dikelompokkan agar baris tabel tidak penuh tombol.
                ActionGroup::make([
                    EditAction::make()->label('Detail'),
                    Action::make('previewExtendedAbstract')
                        ->label('Preview PDF')
                        ->icon('heroicon-o-document-magnifying-glass')
                        ->color('gray')
                        ->visible(fn ($record) => $record->extended_abstract_draft_saved_at || filled($record->abstract))
                        ->url(fn ($record): string => route('admin.submissions.extended-abstract.preview', $record))
                        ->openUrlInNewTab(),
                    Action::make('reviewDirectly')
                        ->label('Review Langsung')
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning')
                        ->visible(fn ($record) => $record->currentReviewPhase() !== null
                            && (auth()->user()?->isSuperadmin() ?? false))
                        ->modalHeading('Review Abstract (langsung oleh admin)')
                        ->schema([
                            Placeholder::make('abstract_preview')
                                ->label('Abstract')
                                ->content(fn ($record): HtmlString => new HtmlString(
                                    '<div class="whitespace-pre-line text-xs leading-6 text-gray-700">'.e($record->abstract ?: '—').'</div>'
                                )),
                            TextInput::make('score')->label('Skor (1–100)')->numeric()->minValue(1)->maxValue(100),
                            Select::make('recommendation')->label('Rekomendasi')->options([
                                'accept' => 'Accept',
                                'minor_revision' => 'Minor Revision',
                                'major_revision' => 'Major Revision',
                                'reject' => 'Reject',
                            ])->required(),
                            Textarea::make('comments_for_author')->label('Komentar untuk Author')->rows(4),
                            Textarea::make('comments_for_committee')->label('Komentar untuk Panitia (internal)')->rows(3),
                        ])
                        ->fillForm(function ($record): array {
                            $review = $record->reviewAssignments()
                                ->where('reviewer_id', auth()->id())
                                ->where('phase', $record->currentReviewPhase())
                                ->first()?->review;

                            return [
                                'score' => $review?->score,
                                'recommendation' => $review?->recommendation,
                                'comments_for_author' => $review?->comments_for_author,
                                'comments_for_committee' => $review?->comments_for_committee,
                            ];
                        })
                        ->action(function (array $data, $record): void {
                            $phase = $record->currentReviewPhase();
                            if (! $phase) {
                                return;
                            }

                            $assignment = $record->reviewAssignments()->firstOrCreate(
                                ['reviewer_id' => auth()->id(), 'phase' => $phase],
                                ['assigned_at' => now(), 'status' => 'pending'],
                            );

                            Review::updateOrCreate(
                                ['review_assignment_id' => $assignment->id],
                                [
                                    'score' => $data['score'] ?? null,
                                    'recommendation' => $data['recommendation'],
                                    'comments_for_author' => $data['comments_for_author'] ?? null,
                                    'comments_for_committee' => $data['comments_for_committee'] ?? null,
                                    'submitted_at' => now(),
                                ],
                            );

                            $assignment->update(['status' => 'completed']);

                            if ($record->status === 'extended_abstract_submitted') {
                                $record->changeStatus('extended_abstract_under_review');
                            }

                            Notification::make()->title('Review admin tersimpan. Lanjutkan ke Keputusan Review.')->success()->send();
                        }),
                    Action::make('downloadFullPaper')
                        ->label('Full Paper')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->visible(fn ($record) => $record->hasFullPaper())
                        ->url(fn ($record) => route('admin.submissions.full-paper.download', $record))
                        ->openUrlInNewTab(),
                    Action::make('changeStatus')
                        ->label('Koreksi Status')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->modalHeading('Koreksi Status Paper')
                        ->modalDescription('Status berubah otomatis lewat "Assign Reviewer" dan "Keputusan Review". Gunakan ini hanya untuk memperbaiki kekeliruan — author menerima email setiap kali status berubah.')
                        ->visible(fn ($record) => ! ($record->currentReviewPhase() !== null
                            && $record->reviewAssignments()
                                ->where('phase', $record->currentReviewPhase())
                                ->where('status', 'completed')
                                ->exists()
                            && ! $record->reviewAssignments()
                                ->where('phase', $record->currentReviewPhase())
                                ->where('status', 'pending')
                                ->exists()))
                        ->schema([
                            Select::make('status')
                                ->label('Ubah status menjadi')
                                ->options(self::MANUAL_STATUS_OPTIONS)
                                ->helperText('Untuk menerima paper, gunakan "Keputusan Review" agar LOA ikut terbit.')
                                ->native(false)
                                ->required(),
                        ])
                        ->action(function (array $data, $record): void {
                            $record->changeStatus($data['status']);

                            Notification::make()->title('Status diperbarui, email dikirim ke author.')->success()->send();
                        }),
                ])
                    ->label('Lainnya')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->hiddenLabel(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Reviewer yang pantas menilai sub-tema paper ini. */
    /** Reviewer yang sedang memegang paper ini pada fase berjalan, bila ada. */
    private static function currentReviewer($record): ?int
    {
        $phase = $record->currentReviewPhase();

        return $phase
            ? $record->reviewAssignments()->where('phase', $phase)->value('reviewer_id')
            : null;
    }

    /** Daftar reviewer yang boleh dipilih untuk paper ini. */
    private static function reviewerOptions($record, bool $showAll): Collection
    {
        return $showAll
            ? User::role('reviewer')->orderBy('name')->pluck('name', 'id')
            : static::expertReviewers($record);
    }

    private static function expertReviewers($record): Collection
    {
        return User::role('reviewer')
            ->expertIn($record->topic_id)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /**
     * Sebutkan sub-temanya, dan katakan terus terang bila belum ada ahlinya —
     * daftar yang mendadak pendek tanpa penjelasan hanya membingungkan.
     */
    private static function sinta3Description($record): string
    {
        $recommended = $record->reviewsRecommendSinta3()
            ? 'Reviewer merekomendasikan paper ini untuk SINTA 3.'
            : 'Tidak ada reviewer yang merekomendasikan SINTA 3 untuk paper ini.';

        return $recommended.' Setelah ditetapkan di sini, penilaian reviewer berikutnya tidak akan mengubahnya lagi. Membuka tawaran menambah biaya penerbitan pada tagihan author bila ia memilihnya.';
    }

    private static function assignDescription($record): string
    {
        $topic = $record->topic?->title;

        if (! $topic) {
            return 'Paper ini belum memilih sub-tema, jadi seluruh reviewer ditampilkan.';
        }

        $experts = static::expertReviewers($record)->count();

        if (static::currentReviewer($record)) {
            return 'Sub-tema: '.$topic.' — reviewer yang dipilih menggantikan penugasan sebelumnya.';
        }

        return $experts > 0
            ? 'Sub-tema: '.$topic.' — '.$experts.' reviewer tersedia untuk sub-tema ini.'
            : 'Sub-tema: '.$topic.' — belum ada reviewer dengan kepakaran ini. Daftar penuh ditampilkan agar paper tidak tertahan.';
    }
}
