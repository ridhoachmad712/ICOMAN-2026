<?php

namespace App\Filament\Resources\Submissions\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class SubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Paper')
                    ->columns(2)
                    ->schema([
                        TextInput::make('submission_number')->disabled(),
                        TextInput::make('status')->disabled(),
                        TextInput::make('title')->disabled()->columnSpanFull(),
                        Placeholder::make('keywords_display')
                            ->label('Keywords')
                            ->content(fn ($record) => filled($record?->keywords) ? implode(', ', $record->keywords) : '—')
                            ->columnSpanFull(),
                        Placeholder::make('extended_abstract_document')
                            ->label('Abstract')
                            ->content(function ($record): HtmlString {
                                if (! $record || (! filled($record->abstract) && ! $record->extended_abstract_draft_saved_at)) {
                                    return new HtmlString('<p class="text-sm italic text-gray-500">Belum diinput oleh author.</p>');
                                }

                                $pdfUrl = route('admin.submissions.extended-abstract.preview', $record);
                                $document = view('components.extended-abstract-document', ['submission' => $record])->render();

                                return new HtmlString(
                                    '<div class="mb-4"><a class="fi-btn fi-btn-color-gray fi-size-sm inline-flex items-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold" href="'.e($pdfUrl).'" target="_blank">Buka Preview PDF</a></div>'.$document,
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Authors')
                    ->schema([
                        Placeholder::make('authors_list')
                            ->hiddenLabel()
                            ->content(function ($record): HtmlString {
                                if (! $record) {
                                    return new HtmlString('—');
                                }
                                $rows = $record->authors()->orderBy('order')->get()->map(function ($a) {
                                    $corr = $a->is_corresponding ? ' <span class="text-primary-600">(corresponding)</span>' : '';

                                    return '<li>'.e($a->name).' &lt;'.e($a->email).'&gt; — '.e($a->affiliation ?? '').$corr.'</li>';
                                })->implode('');

                                return new HtmlString('<ul class="list-disc ps-5 space-y-1">'.$rows.'</ul>');
                            }),
                    ]),

                Section::make('Reviewers & Hasil Review')
                    ->schema([
                        Placeholder::make('reviewers_list')
                            ->hiddenLabel()
                            ->content(function ($record): HtmlString {
                                if (! $record || $record->reviewAssignments->isEmpty()) {
                                    return new HtmlString('<p class="text-sm text-gray-500 italic">Belum ada reviewer yang ditugaskan.</p>');
                                }

                                $items = $record->reviewAssignments->map(function ($ra) {
                                    $rev = $ra->review;
                                    $statusBadge = $ra->status === 'completed'
                                        ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-800">Completed</span>'
                                        : '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-800">Pending</span>';

                                    $rec = $rev?->recommendation
                                        ? ' &bull; <strong>Rekomendasi:</strong> '.ucwords(str_replace('_', ' ', $rev->recommendation))
                                        : '';
                                    $score = $rev?->score ? ' &bull; <strong>Skor:</strong> '.$rev->score.'/100' : '';
                                    $comments = $rev?->comments_for_author
                                        ? '<div class="mt-2 text-xs bg-gray-50 p-3 rounded border border-gray-200"><strong>Komentar untuk Author:</strong><br><span class="whitespace-pre-line text-gray-700">'.e($rev->comments_for_author).'</span></div>'
                                        : '';
                                    $internal = $rev?->comments_for_committee
                                        ? '<div class="mt-1 text-xs bg-blue-50 p-3 rounded border border-blue-200"><strong>Catatan Internal Panitia:</strong><br><span class="whitespace-pre-line text-blue-900">'.e($rev->comments_for_committee).'</span></div>'
                                        : '';

                                    return '<div class="p-3.5 rounded-lg border border-gray-200 bg-white mb-3">'
                                        .'<div class="flex items-center justify-between gap-2 mb-1">'
                                        .'<span class="font-bold text-sm text-gray-900">'.e($ra->reviewer?->name ?? 'Reviewer #'.$ra->reviewer_id).'</span>'
                                        .$statusBadge
                                        .'</div>'
                                        .'<div class="text-xs text-gray-600">Ditugaskan: '.$ra->assigned_at?->format('d M Y H:i').$score.$rec.'</div>'
                                        .$comments
                                        .$internal
                                        .'</div>';
                                })->implode('');

                                return new HtmlString($items);
                            }),
                    ]),
            ]);
    }
}
