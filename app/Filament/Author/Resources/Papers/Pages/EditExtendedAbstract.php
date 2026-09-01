<?php

namespace App\Filament\Author\Resources\Papers\Pages;

use App\Filament\Author\Resources\Papers\PaperResource;
use App\Filament\Shared\RichContent\EquationBlock;
use App\Models\Submission;
use App\Models\Topic;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
            Section::make($id ? 'Petunjuk penulisan' : 'Writing guide')
                ->description($id
                    ? 'Gunakan tombol Blocks → Rumus untuk menyisipkan LaTeX. Gambar dapat ditempel atau diunggah langsung ke editor.'
                    : 'Use Blocks → Equation to insert LaTeX. Images can be pasted or uploaded directly into the editor.')
                ->icon('heroicon-o-information-circle')
                ->collapsible()
                ->collapsed(),
            ...collect(array_keys(Submission::EXTENDED_ABSTRACT_FIELDS))
                ->map(fn (string $field) => Section::make(Submission::EXTENDED_ABSTRACT_FIELDS[$field])
                    ->description(match ($field) {
                        'extended_abstract_abstract' => $id ? 'Ringkasan tujuan, metode, hasil utama, dan kesimpulan.' : 'Summarize the purpose, method, main findings, and conclusion.',
                        'extended_abstract_introduction' => $id ? 'Jelaskan latar belakang, masalah, tujuan, dan kontribusi penelitian.' : 'Explain the background, problem, objective, and contribution.',
                        'extended_abstract_method' => $id ? 'Jelaskan desain, data, sampel, instrumen, dan teknik analisis.' : 'Describe the design, data, sample, instruments, and analysis.',
                        'extended_abstract_results_discussion' => $id ? 'Paparkan hasil dan hubungkan dengan teori atau penelitian terdahulu.' : 'Present the findings and relate them to theory or prior studies.',
                        default => $id ? 'Tuliskan simpulan utama, implikasi, dan rekomendasi.' : 'State the main conclusion, implications, and recommendations.',
                    })
                    ->schema([
                        $this->richEditor($field, $id),
                    ]))
                ->values()
                ->all(),
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
                ->modalHeading(app()->getLocale() === 'id' ? 'Kirim extended abstract?' : 'Submit extended abstract?')
                ->modalDescription(app()->getLocale() === 'id'
                    ? 'Setelah dikirim, naskah dikunci selama proses verifikasi reviewer.'
                    : 'After submission, the manuscript is locked during reviewer verification.')
                ->action(fn () => $this->submitForReview()),
            $this->getCancelFormAction(),
        ];
    }

    public function submitForReview(): void
    {
        $this->authorizeAccess();

        // Save the complete draft first, including paper metadata and the
        // authors relationship, so the submitted PDF always reflects the UI.
        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

        if (! $this->record->refresh()->hasCompleteExtendedAbstract()) {
            Notification::make()
                ->title(app()->getLocale() === 'id' ? 'Extended abstract belum lengkap' : 'Extended abstract is incomplete')
                ->body(app()->getLocale() === 'id' ? 'Kelima bagian wajib diisi sebelum dikirim.' : 'All five sections are required before submission.')
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
            ->title(app()->getLocale() === 'id' ? 'Extended abstract berhasil dikirim' : 'Extended abstract submitted')
            ->success()
            ->send();

        $this->redirect(PaperResource::getUrl('view', ['record' => $this->record]));
    }

    public function updatedData(mixed $value, string $key): void
    {
        if (! array_key_exists($key, Submission::EXTENDED_ABSTRACT_FIELDS)) {
            return;
        }

        $this->record->update([
            $key => $value,
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

    private function richEditor(string $field, bool $id): RichEditor
    {
        return RichEditor::make($field)
            ->hiddenLabel()
            ->json()
            ->live(onBlur: true)
            ->customBlocks([EquationBlock::class])
            ->fileAttachments(true)
            ->fileAttachmentsDisk('local')
            ->fileAttachmentsVisibility('private')
            ->fileAttachmentsDirectory("submissions/{$this->record->id}/extended-abstract")
            ->fileAttachmentsAcceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->fileAttachmentsMaxSize(5120)
            ->resizableImages()
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript'],
                ['h2', 'h3', 'paragraph'],
                ['bulletList', 'orderedList', 'blockquote'],
                ['table', 'attachFiles', 'customBlocks'],
                ['undo', 'redo'],
            ])
            ->helperText($id
                ? 'Format dasar dari Word akan dipertahankan. Maksimum gambar 5 MB (JPG, PNG, atau WebP).'
                : 'Basic formatting from Word will be preserved. Images may be up to 5 MB (JPG, PNG, or WebP).')
            ->columnSpanFull();
    }
}
