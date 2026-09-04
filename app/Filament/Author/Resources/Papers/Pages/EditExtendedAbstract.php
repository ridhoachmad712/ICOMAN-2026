<?php

namespace App\Filament\Author\Resources\Papers\Pages;

use App\Filament\Author\Resources\Papers\PaperResource;
use App\Models\Submission;
use App\Models\Topic;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditExtendedAbstract extends EditRecord
{
    protected static string $resource = PaperResource::class;

    public function getTitle(): string
    {
        return app()->getLocale() === 'id' ? 'Edit Submission' : 'Edit Submission';
    }

    public function getSubheading(): ?string
    {
        return app()->getLocale() === 'id'
            ? 'Semua data masih dapat diubah selama submission belum dikirim ke reviewer.'
            : 'All submission details remain editable until you submit them to the reviewer.';
    }

    public function form(Schema $schema): Schema
    {
        $id = app()->getLocale() === 'id';
        $edition = currentEdition();

        return $schema->columns(1)->components([
            Section::make($id ? 'Informasi paper' : 'Paper information')
                ->description($id
                    ? 'Anda dapat memperbarui judul, topik, dan maksimal lima keywords kapan saja selama masih draft.'
                    : 'You may update the title, topic, and up to five keywords at any time while this is a draft.')
                ->icon('heroicon-o-document-text')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label($id ? 'Judul paper' : 'Paper title')
                        ->required()
                        ->maxLength(500)
                        ->columnSpanFull(),
                    Select::make('topic_id')
                        ->label($id ? 'Topik' : 'Topic')
                        ->options(Topic::query()
                            ->when($edition, fn (Builder $query) => $query->where('edition_id', $edition->id))
                            ->orderBy('order')
                            ->get()
                            ->mapWithKeys(fn (Topic $topic) => [$topic->id => $topic->title]))
                        ->searchable()
                        ->required()
                        ->columnSpanFull(),
                    TagsInput::make('keywords')
                        ->label('Keywords')
                        ->placeholder($id ? 'Ketik keyword lalu tekan Enter' : 'Type a keyword and press Enter')
                        ->helperText($id ? 'Wajib diisi, maksimal 5 keywords.' : 'Required, with a maximum of 5 keywords.')
                        ->required()
                        ->rules(['array', 'min:1', 'max:5'])
                        ->nestedRecursiveRules(['string', 'max:100'])
                        ->columnSpanFull(),
                ]),
            Section::make($id ? 'Daftar penulis' : 'Authors')
                ->description($id
                    ? 'Ubah urutan dan data penulis. Pastikan tepat satu orang ditandai sebagai corresponding author.'
                    : 'Update the author details and order. Make sure exactly one person is marked as corresponding author.')
                ->icon('heroicon-o-users')
                ->schema([
                    Repeater::make('authors')
                        ->hiddenLabel()
                        ->relationship()
                        ->orderColumn('order')
                        ->default(fn () => [[
                            'name' => Filament::auth()->user()?->name,
                            'email' => Filament::auth()->user()?->email,
                            'affiliation' => Filament::auth()->user()?->affiliation,
                            'is_corresponding' => true,
                        ]])
                        ->minItems(1)
                        ->addActionLabel($id ? 'Tambah penulis' : 'Add author')
                        ->reorderable()
                        ->columns(2)
                        ->schema([
                            TextInput::make('name')->label($id ? 'Nama lengkap' : 'Full name')->required()->maxLength(255),
                            TextInput::make('email')->email()->required()->maxLength(255),
                            TextInput::make('affiliation')->label($id ? 'Afiliasi' : 'Affiliation')->maxLength(255),
                            Toggle::make('is_corresponding')->label('Corresponding author'),
                        ]),
                ]),
            Section::make('Abstract')
                ->description($id
                    ? 'Tulis abstract dalam bahasa Inggris, '.Submission::ABSTRACT_MIN_WORDS.'–'.Submission::ABSTRACT_MAX_WORDS.' kata. Ringkas latar belakang, tujuan, metode, hasil utama, dan kesimpulan.'
                    : 'Write the abstract in English, '.Submission::ABSTRACT_MIN_WORDS.'–'.Submission::ABSTRACT_MAX_WORDS.' words. Summarize the background, objective, method, main findings, and conclusion.')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Textarea::make('abstract')
                        ->hiddenLabel()
                        ->rows(14)
                        ->live(onBlur: true)
                        ->maxLength(6000)
                        ->helperText($id
                            ? 'Wajib bahasa Inggris. Panjang '.Submission::ABSTRACT_MIN_WORDS.'–'.Submission::ABSTRACT_MAX_WORDS.' kata.'
                            : 'Must be written in English. Length '.Submission::ABSTRACT_MIN_WORDS.'–'.Submission::ABSTRACT_MAX_WORDS.' words.')
                        ->hint(fn (?string $state): string => $this->wordCount($state).($id ? ' kata' : ' words'))
                        ->hintColor(fn (?string $state): string => $this->wordCountInRange($state) ? 'success' : 'danger')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewPdf')
                ->label(app()->getLocale() === 'id' ? 'Preview PDF' : 'PDF Preview')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('gray')
                ->url(fn (): string => route('author.submissions.extended-abstract.preview', $this->record))
                ->openUrlInNewTab(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label(app()->getLocale() === 'id' ? 'Simpan Perubahan' : 'Save Changes')
                ->icon('heroicon-o-cloud-arrow-up'),
            Action::make('submitForReview')
                ->label(fn () => $this->record->status === 'revision_required'
                    ? (app()->getLocale() === 'id' ? 'Kirim Ulang Revisi' : 'Resubmit Revision')
                    : (app()->getLocale() === 'id' ? 'Kirim ke Reviewer' : 'Submit to Reviewer'))
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(app()->getLocale() === 'id' ? 'Kirim abstract?' : 'Submit abstract?')
                ->modalDescription(app()->getLocale() === 'id'
                    ? 'Setelah dikirim, abstract dikunci selama proses verifikasi reviewer.'
                    : 'After submission, the abstract is locked during reviewer verification.')
                ->action(fn () => $this->submitForReview()),
            $this->getCancelFormAction(),
        ];
    }

    public function submitForReview(): void
    {
        $this->authorizeAccess();

        // Simpan draft lengkap dulu (judul, penulis, abstract) agar PDF & review
        // selalu mencerminkan tampilan terbaru.
        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

        if (! $this->record->refresh()->hasValidAbstract()) {
            Notification::make()
                ->title(app()->getLocale() === 'id' ? 'Abstract belum memenuhi syarat' : 'Abstract does not meet the requirement')
                ->body(app()->getLocale() === 'id'
                    ? 'Abstract wajib '.Submission::ABSTRACT_MIN_WORDS.'–'.Submission::ABSTRACT_MAX_WORDS.' kata sebelum dikirim.'
                    : 'The abstract must be '.Submission::ABSTRACT_MIN_WORDS.'–'.Submission::ABSTRACT_MAX_WORDS.' words before submission.')
                ->danger()
                ->send();

            return;
        }

        DB::transaction(function (): void {
            // Siklus revisi: reviewer yang sudah ditugaskan direset ke `pending`
            // agar menilai ulang versi terbaru. Keputusan panitia otomatis
            // tersembunyi sampai seluruh review baru selesai. Pada kiriman
            // pertama (belum ada reviewer), status jadi extended_abstract_submitted
            // dan admin menugaskan reviewer.
            $assignments = $this->record->reviewAssignments()->where('phase', 'extended_abstract');
            $hasReviewers = (clone $assignments)->exists();
            (clone $assignments)->update(['status' => 'pending']);

            $this->record->update(['extended_abstract_submitted_at' => now()]);
            $this->record->changeStatus($hasReviewers
                ? 'extended_abstract_under_review'
                : 'extended_abstract_submitted');
        });

        Notification::make()
            ->title(app()->getLocale() === 'id' ? 'Abstract berhasil dikirim' : 'Abstract submitted')
            ->success()
            ->send();

        $this->redirect(PaperResource::getUrl('view', ['record' => $this->record]));
    }

    public function updatedData(mixed $value, string $key): void
    {
        if ($key !== 'abstract') {
            return;
        }

        $this->record->update([
            'abstract' => $value,
            'extended_abstract_draft_saved_at' => now(),
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['extended_abstract_draft_saved_at'] = now();

        return $data;
    }

    protected function beforeSave(): void
    {
        $correspondingAuthors = collect($this->data['authors'] ?? [])
            ->where('is_corresponding', true)
            ->count();

        if ($correspondingAuthors !== 1) {
            throw ValidationException::withMessages([
                'data.authors' => app()->getLocale() === 'id'
                    ? 'Tandai tepat satu corresponding author.'
                    : 'Mark exactly one corresponding author.',
            ]);
        }
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return app()->getLocale() === 'id' ? 'Draft tersimpan' : 'Draft saved';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('extended-abstract', ['record' => $this->record]);
    }

    private function wordCount(?string $text): int
    {
        $text = trim((string) $text);

        return $text === '' ? 0 : count(preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY));
    }

    private function wordCountInRange(?string $text): bool
    {
        $count = $this->wordCount($text);

        return $count >= Submission::ABSTRACT_MIN_WORDS && $count <= Submission::ABSTRACT_MAX_WORDS;
    }

    /** Resource ini tidak punya halaman index; breadcrumb mengarah ke Dashboard. */
    public function getBreadcrumbs(): array
    {
        return [
            \App\Filament\Author\Pages\AuthorDashboard::getUrl(panel: 'author') => 'Dashboard',
            $this->getTitle(),
        ];
    }
}
