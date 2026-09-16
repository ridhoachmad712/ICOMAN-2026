<?php

namespace App\Filament\Resources\Submissions\Schemas;

use App\Models\Submission;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\HtmlString;

class SubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            // Dua kolom dengan pembagian tugas yang jelas: yang berupa prosa
            // panjang — naskah dan komentar reviewer — mengisi kolom lebar,
            // sedangkan keterangan pendek menumpuk di kolom sempit. Sebelumnya
            // semuanya bertumpuk di satu sisi dan sisi lainnya nyaris kosong.
            ->columns(3)
            ->components([
                Group::make()
                    ->columnSpan(2)
                    ->schema([
                        Section::make()
                            ->schema([
                                Text::make(fn ($record): string => $record?->title ?: 'Tanpa judul')
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::Bold),
                                Placeholder::make('keywords_display')
                                    ->label('Keywords')
                                    ->content(fn ($record): string => filled($record?->keywords)
                                        ? implode(', ', $record->keywords)
                                        : '—'),
                            ]),

                        Section::make('Extended Abstract')
                            ->headerActions([
                                Action::make('previewPdf')
                                    ->label('Buka Preview PDF')
                                    ->icon('heroicon-o-document-arrow-down')
                                    ->color('gray')
                                    ->url(fn ($record): ?string => $record
                                        ? route('admin.submissions.extended-abstract.preview', $record)
                                        : null)
                                    ->openUrlInNewTab()
                                    ->visible(fn ($record): bool => static::hasAbstract($record)),
                            ])
                            ->schema([
                                Placeholder::make('extended_abstract_document')
                                    ->hiddenLabel()
                                    ->content(function ($record): HtmlString {
                                        if (! static::hasAbstract($record)) {
                                            return new HtmlString('Belum diinput oleh author.');
                                        }

                                        return new HtmlString(
                                            view('components.extended-abstract-document', ['submission' => $record])->render()
                                        );
                                    }),
                            ]),

                        Section::make('Reviewer & Hasil Review')
                            ->schema(fn ($record): array => static::reviewBlocks($record)),
                    ]),

                Group::make()
                    ->columnSpan(1)
                    ->schema([
                        Section::make('Ringkasan')
                            ->schema([
                                Placeholder::make('submission_number_display')
                                    ->label('Kode')
                                    ->content(fn ($record): string => $record?->submission_number ?: '—'),
                                Placeholder::make('status_display')
                                    ->label('Status')
                                    ->content(fn ($record): string => Submission::STATUS_LABELS[$record?->status] ?? '—'),
                                Placeholder::make('topic_display')
                                    ->label('Sub-tema')
                                    ->content(fn ($record): string => $record?->topic?->title ?: 'Belum dipilih'),
                                Placeholder::make('journal_target_display')
                                    ->label('Target jurnal')
                                    ->content(fn ($record): string => $record?->journalTargetLabel() ?? '—'),
                                Placeholder::make('submitted_at_display')
                                    ->label('Dikirim')
                                    ->content(fn ($record): string => $record?->extended_abstract_submitted_at?->format('d M Y H:i')
                                        ?? $record?->submitted_at?->format('d M Y H:i')
                                        ?? 'Belum dikirim'),
                                Placeholder::make('loa_display')
                                    ->label('LOA terbit')
                                    ->content(fn ($record): string => $record?->loa_issued_at?->format('d M Y') ?? 'Belum terbit'),
                            ]),

                        Section::make('Authors')
                            ->schema(fn ($record): array => static::authorBlocks($record)),
                    ]),
            ]);
    }

    /**
     * Daftar author dan hasil review dirakit dari komponen Filament, bukan HTML
     * sendiri: panel admin memakai CSS bawaan Filament, jadi kelas Tailwind
     * lepas yang ditulis di sini tidak ikut dikompilasi — badge "Completed"
     * sebelumnya cuma muncul sebagai teks polos yang menempel ke nama reviewer.
     *
     * @return array<int, Component>
     */
    private static function authorBlocks($record): array
    {
        $authors = $record?->authors()->orderBy('order')->get();

        if (blank($authors)) {
            return [Text::make('Belum ada author yang dicatat.')->color('gray')];
        }

        return $authors->map(fn ($author) => Section::make($author->name)
            ->description($author->is_corresponding ? 'Corresponding author' : null)
            ->compact()
            ->secondary()
            ->schema(array_values(array_filter([
                Text::make($author->email)->size(TextSize::Small)->color('gray'),
                $author->affiliation
                    ? Text::make($author->affiliation)->size(TextSize::Small)->color('gray')
                    : null,
            ]))))->all();
    }

    /** @return array<int, Component> */
    private static function reviewBlocks($record): array
    {
        if (! $record || $record->reviewAssignments->isEmpty()) {
            return [Text::make('Belum ada reviewer yang ditugaskan.')->color('gray')];
        }

        return $record->reviewAssignments->map(function ($assignment) {
            $review = $assignment->review;

            $facts = [];
            if ($assignment->assigned_at) {
                $facts[] = 'Ditugaskan '.$assignment->assigned_at->format('d M Y');
            }
            if ($review?->score) {
                $facts[] = 'Skor '.$review->score.'/100';
            }
            if ($review?->recommendation) {
                $facts[] = ucwords(str_replace('_', ' ', $review->recommendation));
            }

            $isDone = $assignment->status === 'completed';

            return Section::make($assignment->reviewer?->name ?? 'Reviewer #'.$assignment->reviewer_id)
                ->description($facts ? implode(' • ', $facts) : null)
                ->compact()
                ->secondary()
                ->afterHeader([
                    Text::make($isDone ? 'Completed' : 'Pending')
                        ->badge()
                        ->color($isDone ? 'success' : 'warning'),
                ])
                ->schema(array_values(array_filter([
                    $review?->comments_for_author
                        ? Placeholder::make('comments_for_author_'.$assignment->id)
                            ->label('Untuk author')
                            ->content($review->comments_for_author)
                        : null,
                    $review?->comments_for_committee
                        ? Placeholder::make('comments_for_committee_'.$assignment->id)
                            ->label('Catatan internal panitia')
                            ->content($review->comments_for_committee)
                        : null,
                ])));
        })->all();
    }

    private static function hasAbstract($record): bool
    {
        return (bool) $record && (filled($record->abstract) || $record->extended_abstract_draft_saved_at);
    }
}
