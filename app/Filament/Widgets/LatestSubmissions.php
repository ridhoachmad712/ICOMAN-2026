<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Submissions\SubmissionResource;
use App\Models\Submission;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Lima abstract yang terakhir masuk.
 *
 * Sebelumnya tabel ini jalan buntu: barisnya tidak bisa dibuka, reviewernya
 * tidak terlihat, dan draft yang belum pernah dikirim ikut terurut di antaranya
 * — draft belum punya `submitted_at`, jadi urutannya bergantung pada kebetulan.
 * Sekarang yang tampil hanya yang benar-benar sudah dikirim, lengkap dengan
 * siapa yang menilai dan sudah berapa lama menunggu.
 */
class LatestSubmissions extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->managesSubmissions() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Abstract Terakhir Masuk')
            ->description('Klik satu baris untuk membuka papernya.')
            ->query(
                Submission::query()
                    ->ofCurrentEdition()
                    // Yang memisahkan draft dari kiriman adalah statusnya.
                    // `submitted_at` tidak bisa dipakai: model mengisinya sejak
                    // barisnya dibuat, jadi draft pun punya tanggal.
                    ->whereNot('status', 'extended_abstract_draft')
                    ->with(['author', 'reviewAssignments.reviewer'])
                    ->latest('submitted_at')
                    ->limit(5)
            )
            ->paginated(false)
            ->recordUrl(fn (Submission $record): string => SubmissionResource::getUrl('edit', ['record' => $record]))
            ->emptyStateHeading('Belum ada abstract yang masuk')
            ->emptyStateDescription('Abstract yang dikirim author akan muncul di sini.')
            ->columns([
                TextColumn::make('submission_number')->label('Kode'),

                TextColumn::make('title')->label('Judul')->limit(40)->wrap(),

                TextColumn::make('author.name')->label('Submitter'),

                TextColumn::make('reviewer')
                    ->label('Reviewer')
                    ->badge()
                    ->color(fn (?string $state): string => $state === null ? 'danger' : 'info')
                    ->state(fn (Submission $record): ?string => $record->reviewAssignments
                        ->sortByDesc('id')
                        ->first()?->reviewer?->name)
                    // Tanpa penanda ini, paper yang belum ditugaskan terlihat
                    // sama saja dengan sel kosong biasa.
                    ->placeholder('Belum ditugaskan'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Submission::STATUS_LABELS[$state] ?? ucwords(str_replace('_', ' ', $state)))
                    ->color(fn (string $state) => match ($state) {
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        'extended_abstract_submitted', 'extended_abstract_under_review' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('submitted_at')
                    ->label('Masuk')
                    ->dateTime('d M Y H:i')
                    // Lamanya menunggu yang menunjukkan ada yang tertahan;
                    // tanggal saja menuntut pembacanya berhitung sendiri.
                    ->description(fn (Submission $record): string => $record->submitted_at?->diffForHumans() ?? '—'),
            ]);
    }
}
