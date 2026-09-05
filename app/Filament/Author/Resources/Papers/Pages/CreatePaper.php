<?php

namespace App\Filament\Author\Resources\Papers\Pages;

use App\Filament\Author\Resources\Papers\PaperResource;
use App\Models\Topic;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Validation\ValidationException;

class CreatePaper extends CreateRecord
{
    use HasWizard;

    protected static string $resource = PaperResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return app()->getLocale() === 'id' ? 'Mulai Abstract' : 'Start Abstract';
    }

    public function getSubheading(): ?string
    {
        return app()->getLocale() === 'id'
            ? 'Lengkapi identitas paper terlebih dahulu. Setelah disimpan, Anda langsung masuk ke editor abstract.'
            : 'Complete the paper identity first. After saving, you will go directly to the abstract editor.';
    }

    public function getSteps(): array
    {
        $id = app()->getLocale() === 'id';
        $edition = currentEdition();

        return [
            Step::make($id ? 'Data paper' : 'Paper details')
                ->description($id ? 'Judul, topik, dan keywords' : 'Title, topic, and keywords')
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
                            ->when($edition, fn ($query) => $query->where('edition_id', $edition->id))
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
            Step::make($id ? 'Data penulis' : 'Author details')
                ->description($id ? 'Urutan dan corresponding author' : 'Order and corresponding author')
                ->icon('heroicon-o-users')
                ->schema([
                    Repeater::make('authors')
                        ->label($id ? 'Daftar penulis' : 'Author list')
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
        ];
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label(app()->getLocale() === 'id' ? 'Lanjut Menulis' : 'Continue Writing')
            ->icon('heroicon-o-arrow-right');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $edition = currentEdition();

        if (! $edition) {
            throw ValidationException::withMessages([
                'data.title' => app()->getLocale() === 'id' ? 'Belum ada edisi konferensi aktif.' : 'There is no active conference edition.',
            ]);
        }

        $data['edition_id'] = $edition->id;
        $data['author_id'] = Filament::auth()->id();
        $data['abstract'] = '';
        $data['status'] = 'extended_abstract_draft';
        $data['submitted_at'] = now();

        return $data;
    }

    protected function beforeCreate(): void
    {
        $edition = currentEdition();
        app(\App\Services\ConferenceDeadlines::class)->assertOpen('abstract', $edition?->id, 'data.title');
        $alreadySubmitted = $edition && Filament::auth()->user()
            ?->submissions()
            ->where('edition_id', $edition->id)
            ->exists();

        if ($alreadySubmitted) {
            throw ValidationException::withMessages([
                'data.title' => app()->getLocale() === 'id'
                    ? 'Setiap akun hanya dapat mengirim satu paper pada edisi konferensi ini.'
                    : 'Each account may submit only one paper in this conference edition.',
            ]);
        }

        $authors = collect($this->data['authors'] ?? []);
        if ($authors->where('is_corresponding', true)->count() !== 1) {
            throw ValidationException::withMessages([
                'data.authors' => app()->getLocale() === 'id'
                    ? 'Tandai tepat satu corresponding author.'
                    : 'Mark exactly one corresponding author.',
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return PaperResource::getUrl('extended-abstract', ['record' => $this->record]);
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
