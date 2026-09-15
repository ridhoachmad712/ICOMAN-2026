<?php

namespace App\Filament\Resources\ReviewAssignments\Pages;

use App\Filament\Resources\ReviewAssignments\ReviewAssignmentResource;
use App\Models\Review;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Halaman penilaian: naskah di kiri, formulir di kanan.
 *
 * Sebelumnya semuanya dijejalkan ke satu modal — naskah, tautan PDF, skor, dan
 * dua kotak komentar — sehingga reviewer tidak bisa membaca sambil menulis.
 */
class AssessSubmission extends Page
{
    // Halaman resource dengan parameter {record} butuh trait ini agar route
    // model binding-nya bekerja.
    use InteractsWithRecord;

    protected static string $resource = ReviewAssignmentResource::class;

    protected string $view = 'filament.resources.review-assignments.pages.assess-submission';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->record->loadMissing(['submission.authors', 'submission.topic', 'review']);

        abort_unless($this->canAssess(), 403);

        $this->form->fill([
            'score' => $this->record->review?->score,
            'recommendation' => $this->record->review?->recommendation,
            'recommends_sinta3' => (bool) $this->record->review?->recommends_sinta3,
            'comments_for_author' => $this->record->review?->comments_for_author,
            'comments_for_committee' => $this->record->review?->comments_for_committee,
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->submission?->submission_number ?? __('review.assessment');
    }

    public function getSubheading(): ?string
    {
        return $this->record->submission?->title;
    }

    /** Hanya reviewer yang ditugaskan — dan superadmin — yang boleh menilai. */
    private function canAssess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->id === $this->record->reviewer_id || $user->isSuperadmin()));
    }

    /** Reviewer murni membaca naskah tanpa identitas penulis. */
    public function isBlind(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasRole('reviewer') && ! $user->hasAnyRole(['superadmin', 'admin_registrasi']));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('review.assessment'))
                    ->schema([
                        TextInput::make('score')
                            ->label('Score (1–100)')
                            ->helperText(__('review.score_help'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100),

                        Select::make('recommendation')
                            ->label('Recommendation')
                            ->options([
                                'accept' => 'Accept',
                                'minor_revision' => 'Minor Revision',
                                'major_revision' => 'Major Revision',
                                'reject' => 'Reject',
                            ])
                            ->helperText(__('review.recommendation_help'))
                            ->required(),

                        Toggle::make('recommends_sinta3')
                            ->label('Direkomendasikan untuk Jurnal SINTA 3')
                            ->helperText(__('review.sinta3_help'))
                            ->visible(fn (): bool => $this->record->phase === 'extended_abstract')
                            ->default(false),

                        Textarea::make('comments_for_author')
                            ->label('Comments for Author')
                            ->helperText(__('review.comments_author_help'))
                            ->rows(6),

                        Textarea::make('comments_for_committee')
                            ->label('Comments for Committee')
                            ->helperText(__('review.comments_committee_help'))
                            ->rows(4),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless($this->canAssess(), 403);

        $data = $this->form->getState();

        Review::updateOrCreate(
            ['review_assignment_id' => $this->record->id],
            [
                'score' => $data['score'] ?? null,
                'recommendation' => $data['recommendation'],
                'recommends_sinta3' => (bool) ($data['recommends_sinta3'] ?? false),
                'comments_for_author' => $data['comments_for_author'] ?? null,
                'comments_for_committee' => $data['comments_for_committee'] ?? null,
                'submitted_at' => now(),
            ],
        );

        $this->record->update(['status' => 'completed']);

        Notification::make()
            ->title(__('review.saved'))
            ->body(__('review.saved_body'))
            ->success()
            ->send();

        $this->redirect(ReviewAssignmentResource::getUrl('index'));
    }
}
