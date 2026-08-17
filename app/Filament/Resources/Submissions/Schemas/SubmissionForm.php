<?php

namespace App\Filament\Resources\Submissions\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
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
                        Textarea::make('abstract')->disabled()->rows(5)->columnSpanFull(),
                        Textarea::make('abstract_id')->label('Abstract (ID)')->disabled()->rows(4)->columnSpanFull(),
                        Placeholder::make('paper_file')
                            ->label('Paper file')
                            ->content(function ($record): HtmlString {
                                $url = $record?->getFirstMediaUrl('paper');

                                return new HtmlString($url
                                    ? '<a href="'.$url.'" target="_blank" class="text-primary-600 underline">Download paper</a>'
                                    : '—');
                            }),
                        Placeholder::make('camera_ready_file')
                            ->label('Camera-ready file')
                            ->content(function ($record): HtmlString {
                                $url = $record?->getFirstMediaUrl('camera_ready');

                                return new HtmlString($url
                                    ? '<a href="'.$url.'" target="_blank" class="text-primary-600 underline">Download camera-ready</a>'
                                    : '—');
                            }),
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
            ]);
    }
}
