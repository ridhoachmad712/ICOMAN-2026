<?php

namespace App\Filament\Author\Resources\Papers\Pages;

use App\Filament\Author\Pages\AuthorDashboard;
use App\Filament\Author\Resources\Papers\PaperResource;
use App\Models\Submission;
use App\Models\Topic;
use App\Services\ConferenceDeadlines;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Js;
use Illuminate\Validation\ValidationException;

class EditExtendedAbstract extends EditRecord
{
    protected static string $resource = PaperResource::class;

    public function getTitle(): string
    {
        return app()->getLocale() === 'id' ? 'Tulis Abstract' : 'Write Abstract';
    }

    public function getSubheading(): ?string
    {
        return app()->getLocale() === 'id'
            ? 'Isi informasi paper dan abstract, simpan draft, periksa PDF, lalu kirim ke reviewer. Naskah lengkap diunggah sebagai berkas setelah LOA terbit dan pembayaran lunas.'
            : 'Complete the paper details and abstract, save a draft, check the PDF, then submit it for review. The full paper is uploaded as a file after the LOA is issued and payment is complete.';
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
            $this->abstractSection($id),
        ]);
    }

    private function abstractSection(bool $id): Section
    {
        return Section::make('Abstract')
            ->description($id
                ? 'Ringkas latar belakang, tujuan, metode, hasil, dan kesimpulan dalam '.Submission::ABSTRACT_MIN_WORDS.'–'.Submission::ABSTRACT_MAX_WORDS.' kata berbahasa Inggris.'
                : 'Summarize the background, objective, method, findings, and conclusion in '.Submission::ABSTRACT_MIN_WORDS.'–'.Submission::ABSTRACT_MAX_WORDS.' English words.')
            ->icon('heroicon-o-document-text')
            ->schema([
                Textarea::make('abstract')
                    ->hiddenLabel()
                    ->rows(14)
                    ->autosize()
                    ->live(onBlur: true)
                    ->hint(fn ($state): string => $this->wordCount($state).($id ? ' kata' : ' words'))
                    ->hintColor(fn ($state): string => $this->wordCountInRange($state) ? 'success' : 'danger')
                    ->helperText($id
                        ? 'Teks biasa. Pisahkan paragraf dengan baris kosong.'
                        : 'Plain text. Separate paragraphs with a blank line.'),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewPdf')
                ->label(app()->getLocale() === 'id' ? 'Simpan & Preview PDF' : 'Save & Preview PDF')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('gray')
                ->action(function (): void {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $this->js('window.open('.Js::from(route('author.submissions.extended-abstract.preview', $this->record)).', "_blank")');
                }),
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
                    ? 'Pastikan abstract dan preview PDF sudah benar. Setelah dikirim, abstract dikunci selama proses review.'
                    : 'Check the abstract and the PDF preview. After submission, the abstract is locked during review.')
                ->action(fn () => $this->submitForReview()),
            $this->getCancelFormAction(),
        ];
    }

    public function submitForReview(): void
    {
        $this->record->refresh();
        $this->authorizeAccess();
        app(ConferenceDeadlines::class)->assertOpen($this->record->status === 'revision_required' ? 'revision' : 'abstract', $this->record->edition_id, 'data.abstract');

        // Simpan draft lengkap dulu (judul, penulis, abstract) agar PDF & review
        // selalu mencerminkan tampilan terbaru.
        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

        if (! $this->record->refresh()->hasValidExtendedAbstract()) {
            Notification::make()
                ->title(app()->getLocale() === 'id' ? 'Abstract belum sesuai' : 'The abstract is not valid yet')
                ->body(app()->getLocale() === 'id'
                    ? 'Abstract wajib '.Submission::ABSTRACT_MIN_WORDS.'–'.Submission::ABSTRACT_MAX_WORDS.' kata. Saat ini '.$this->record->abstractWordCount().' kata.'
                    : 'The abstract must be '.Submission::ABSTRACT_MIN_WORDS.'–'.Submission::ABSTRACT_MAX_WORDS.' words. It currently has '.$this->record->abstractWordCount().' words.')
                ->danger()
                ->send();

            return;
        }

        DB::transaction(function (): void {
            $this->record->snapshot('abstract_submitted');
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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['abstract'] = trim((string) ($data['abstract'] ?? ''));
        $data['extended_abstract_draft_saved_at'] = now();

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Draft dari editor lima bagian lama: bila kolom teks kosong, ambil
        // isi bagian Abstract-nya agar author tidak mulai dari kolom kosong.
        if (blank($data['abstract'] ?? null) && is_array($data['extended_abstract_abstract'] ?? null)) {
            $data['abstract'] = $this->richText($data['extended_abstract_abstract']);
        }

        return $data;
    }

    protected function beforeSave(): void
    {
        $this->record->refresh();
        abort_unless(PaperResource::canEdit($this->record), 403);
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

    private function richText(mixed $state): string
    {
        return trim(RichContentRenderer::make(is_array($state) ? $state : null)->toText());
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
            AuthorDashboard::getUrl(panel: 'author') => 'Dashboard',
            $this->getTitle(),
        ];
    }
}
