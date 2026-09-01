<?php

namespace App\Filament\Widgets;

use App\Models\Submission;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestSubmissions extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['superadmin', 'content_admin']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Submission Terbaru')
            ->query(Submission::query()->with('author')->latest('submitted_at')->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('submission_number')->label('No.'),
                TextColumn::make('title')->limit(40)->wrap(),
                TextColumn::make('author.name')->label('Submitter'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Submission::STATUS_LABELS[$state] ?? ucwords(str_replace('_', ' ', $state)))
                    ->color(fn (string $state) => match ($state) {
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        'extended_abstract_submitted', 'extended_abstract_under_review' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('submitted_at')->dateTime('d M Y H:i'),
            ]);
    }
}
