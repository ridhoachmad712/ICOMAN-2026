<?php

namespace App\Filament\Author\Resources\Papers\Pages;

use App\Filament\Author\Pages\AuthorDashboard;
use App\Filament\Author\Resources\Papers\PaperResource;
use App\Models\Topic;
use App\Services\ConferenceDeadlines;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;

class CreatePaper extends CreateRecord
{
    use HasWizard;

    protected static string $resource = PaperResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * Cegat sebelum formulirnya sempat diisi. Sebelumnya penolakan baru terjadi
     * saat tombol simpan ditekan, dan pesannya melekat pada isian di langkah
     * pertama wizard — tak terlihat dari langkah kedua, sehingga tombolnya
     * tampak sekadar tidak berfungsi.
     */
    public function mount(): void
    {
        // Sengaja sebelum parent::mount(): di sana ada pemeriksaan izin yang
        // membalas 403 untuk akun yang sudah punya paper — layar buntu tanpa
        // penjelasan. Antar mereka ke papernya sendiri.
        $edition = currentEdition();
        $isId = app()->getLocale() === 'id';

        $existing = $edition
            ? Filament::auth()->user()?->submissions()->where('edition_id', $edition->id)->latest('submitted_at')->first()
            : null;

        if ($existing) {
            Notification::make()
                ->title($isId ? 'Anda sudah memiliki paper pada edisi ini.' : 'You already have a paper in this edition.')
                ->body($isId
                    ? 'Setiap akun hanya dapat mengirim satu paper. Lanjutkan mengerjakan paper yang sudah ada.'
                    : 'Each account may submit only one paper. Continue working on the paper you already have.')
                ->warning()
                ->persistent()
                ->send();

            $this->redirect(PaperResource::getUrl('view', ['record' => $existing], panel: 'author'));

            return;
        }

        if (! app(ConferenceDeadlines::class)->isOpen('abstract', $edition?->id)) {
            Notification::make()
                ->title($isId ? 'Pengiriman abstract sudah ditutup.' : 'Abstract submission has closed.')
                ->body($isId
                    ? 'Tenggat tahap ini telah berakhir. Hubungi panitia bila Anda memerlukan bantuan.'
                    : 'The deadline for this stage has passed. Contact the committee if you need assistance.')
                ->danger()
                ->persistent()
                ->send();

            $this->redirect(AuthorDashboard::getUrl(panel: 'author'));

            return;
        }

        parent::mount();
    }

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
            // Sama seperti penolakan lain: lewat notifikasi, bukan galat yang
            // menempel pada isian di langkah yang sedang tidak terlihat.
            $this->refuse(
                app()->getLocale() === 'id' ? 'Belum ada edisi konferensi aktif.' : 'There is no active conference edition.',
                app()->getLocale() === 'id'
                    ? 'Hubungi panitia agar edisi konferensi diaktifkan lebih dulu.'
                    : 'Contact the committee so the conference edition can be activated first.',
            );
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
        $isId = app()->getLocale() === 'id';

        // Pagar terakhir: keadaan bisa berubah antara halaman dibuka dan
        // disimpan. Pesannya lewat notifikasi supaya terbaca dari langkah mana
        // pun wizard sedang berada.
        if (! app(ConferenceDeadlines::class)->isOpen('abstract', $edition?->id)) {
            $this->refuse(
                $isId ? 'Pengiriman abstract sudah ditutup.' : 'Abstract submission has closed.',
                $isId
                    ? 'Tenggat tahap ini telah berakhir. Hubungi panitia bila Anda memerlukan bantuan.'
                    : 'The deadline for this stage has passed. Contact the committee if you need assistance.',
            );
        }

        $alreadySubmitted = $edition && Filament::auth()->user()
            ?->submissions()
            ->where('edition_id', $edition->id)
            ->exists();

        if ($alreadySubmitted) {
            $this->refuse(
                $isId ? 'Anda sudah memiliki paper pada edisi ini.' : 'You already have a paper in this edition.',
                $isId
                    ? 'Setiap akun hanya dapat mengirim satu paper pada edisi konferensi ini.'
                    : 'Each account may submit only one paper in this conference edition.',
            );
        }

        $corresponding = collect($this->data['authors'] ?? [])->where('is_corresponding', true)->count();

        if ($corresponding !== 1) {
            $this->refuse(
                $isId ? 'Corresponding author belum tepat.' : 'The corresponding author is not set correctly.',
                $isId
                    ? 'Tandai tepat satu penulis sebagai corresponding author pada langkah Data penulis.'
                    : 'Mark exactly one author as the corresponding author in the Author details step.',
            );
        }
    }

    /**
     * Isian wajib yang kosong bisa berada di langkah yang sedang tidak terlihat,
     * dan galatnya menempel pada isian itu — dari langkah lain tombol simpan
     * jadi tampak sekadar tidak berfungsi. Sebutkan langkah mana yang harus
     * diperiksa.
     */
    protected function onValidationError(ValidationException $exception): void
    {
        parent::onValidationError($exception);

        $isId = app()->getLocale() === 'id';
        $field = (string) array_key_first($exception->errors());
        $message = $exception->errors()[$field][0] ?? '';

        $onAuthorStep = str_contains($field, 'authors');
        $step = $onAuthorStep
            ? ($isId ? 'Data penulis' : 'Author details')
            : ($isId ? 'Data paper' : 'Paper details');

        Notification::make()
            ->title($isId ? 'Ada isian yang belum lengkap.' : 'Some details are still missing.')
            ->body($isId
                ? 'Periksa kembali langkah "'.$step.'". '.$message
                : 'Check the "'.$step.'" step again. '.$message)
            ->danger()
            ->persistent()
            ->send();
    }

    /** Hentikan penyimpanan dengan alasan yang benar-benar terbaca penulis. */
    private function refuse(string $title, string $body): never
    {
        Notification::make()->title($title)->body($body)->danger()->persistent()->send();

        throw new Halt;
    }

    protected function getRedirectUrl(): string
    {
        return PaperResource::getUrl('extended-abstract', ['record' => $this->record]);
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
