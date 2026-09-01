<?php

namespace App\Filament\Author\Resources\Papers;

use App\Filament\Author\Resources\Papers\Pages\CreatePaper;
use App\Filament\Author\Resources\Papers\Pages\EditExtendedAbstract;
use App\Filament\Author\Resources\Papers\Pages\ListPapers;
use App\Filament\Author\Resources\Papers\Pages\ViewPaper;
use App\Models\Submission;
use App\Models\Topic;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaperResource extends Resource
{
    protected static ?string $model = Submission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $slug = 'papers';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return app()->getLocale() === 'id' ? 'Abstract Saya' : 'My Abstract';
    }

    public static function getModelLabel(): string
    {
        return app()->getLocale() === 'id' ? 'abstract' : 'abstract';
    }

    public static function form(Schema $schema): Schema
    {
        $edition = currentEdition();

        return $schema->components([
            Section::make(app()->getLocale() === 'id' ? 'Informasi Paper' : 'Paper Information')
                ->description(app()->getLocale() === 'id'
                    ? 'Masukkan judul, topik, dan maksimal lima keywords.'
                    : 'Enter the title, topic, and up to five keywords.')
                ->schema([
                    TextInput::make('title')
                        ->label(app()->getLocale() === 'id' ? 'Judul paper' : 'Paper title')
                        ->required()
                        ->maxLength(500),
                    Select::make('topic_id')
                        ->label(app()->getLocale() === 'id' ? 'Topik' : 'Topic')
                        ->options(Topic::query()
                            ->when($edition, fn (Builder $query) => $query->where('edition_id', $edition->id))
                            ->orderBy('order')
                            ->get()
                            ->mapWithKeys(fn (Topic $topic) => [$topic->id => $topic->title]))
                        ->searchable()
                        ->required(),
                    TagsInput::make('keywords')
                        ->label('Keywords')
                        ->required()
                        ->rules(['array', 'min:1', 'max:5'])
                        ->nestedRecursiveRules(['string', 'max:100']),
                ]),
            Section::make(app()->getLocale() === 'id' ? 'Daftar Penulis' : 'Authors')
                ->description(app()->getLocale() === 'id'
                    ? 'Tambahkan semua penulis sesuai urutan pada naskah. Tandai satu corresponding author.'
                    : 'Add every author in manuscript order and mark one corresponding author.')
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
                        ->addActionLabel(app()->getLocale() === 'id' ? 'Tambah penulis' : 'Add author')
                        ->reorderable()
                        ->columns(2)
                        ->schema([
                            TextInput::make('name')->label(app()->getLocale() === 'id' ? 'Nama lengkap' : 'Full name')->required()->maxLength(255),
                            TextInput::make('email')->email()->required()->maxLength(255),
                            TextInput::make('affiliation')->label(app()->getLocale() === 'id' ? 'Afiliasi' : 'Affiliation')->maxLength(255),
                            Toggle::make('is_corresponding')->label('Corresponding author'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(app()->getLocale() === 'id' ? 'Paper' : 'Paper')
                    ->description(fn (Submission $record) => 'Paper #'.str_pad((string) $record->id, 5, '0', STR_PAD_LEFT))
                    ->searchable()
                    ->wrap()
                    ->limit(80),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => app()->getLocale() === 'id' ? match ($state) {
                    'extended_abstract_draft' => 'Draft abstract',
                    'abstract_submitted' => 'Abstrak terkirim', 'abstract_under_review' => 'Abstrak direview',
                    'abstract_approved' => 'Lolos review', 'extended_abstract_submitted' => 'Abstract terkirim',
                    'extended_abstract_under_review' => 'Verifikasi reviewer', 'revision_required' => 'Perlu revisi',
                    'accepted' => 'Accepted',
                    'rejected' => 'Tidak lolos', default => ucwords(str_replace('_', ' ', $state)),
                } : ucwords(str_replace('_', ' ', $state)))
                    ->color(fn (string $state) => match ($state) {
                        'accepted' => 'success', 'rejected' => 'danger',
                        'revision_required' => 'warning',
                        'extended_abstract_submitted' => 'warning', default => 'info',
                    }),
                TextColumn::make('submitted_at')->label(app()->getLocale() === 'id' ? 'Tanggal kirim' : 'Submitted')->date('d M Y')->sortable()->visibleFrom('sm'),
            ])
            ->recordUrl(fn (Submission $record) => static::getUrl(
                // Draft baru langsung ke editor; status lain (termasuk revisi)
                // ke halaman detail dulu agar author membaca catatan reviewer.
                $record->status === 'extended_abstract_draft' ? 'extended-abstract' : 'view',
                ['record' => $record],
            ))
            ->defaultSort('submitted_at', 'desc')
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateHeading(app()->getLocale() === 'id' ? 'Belum ada abstract' : 'No abstract yet')
            ->emptyStateDescription(app()->getLocale() === 'id'
                ? 'Mulai abstract untuk masuk ke proses review. Setiap akun memiliki kuota satu paper per edisi.'
                : 'Start your abstract to begin review. Each account has a one-paper quota per edition.');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('author_id', Filament::auth()->id())
            ->with(['topic', 'authors']);
    }

    public static function canCreate(): bool
    {
        $author = Filament::auth()->user();
        $edition = currentEdition();

        return (bool) ($author?->isPresenter()
            && $edition
            && ! $author->submissions()->where('edition_id', $edition->id)->exists());
    }

    public static function canEdit($record): bool
    {
        $author = Filament::auth()->user();

        return (bool) ($author
            && $record->author_id === $author->id
            && in_array($record->status, Submission::AUTHOR_EDITABLE_STATUSES, true));
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPapers::route('/'),
            'create' => CreatePaper::route('/create'),
            'view' => ViewPaper::route('/{record}'),
            'extended-abstract' => EditExtendedAbstract::route('/{record}/extended-abstract'),
        ];
    }
}
