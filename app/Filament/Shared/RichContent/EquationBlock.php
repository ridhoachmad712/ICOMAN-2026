<?php

namespace App\Filament\Shared\RichContent;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;

class EquationBlock extends RichContentCustomBlock
{
    public static function getId(): string
    {
        return 'equation';
    }

    public static function getLabel(): string
    {
        return app()->getLocale() === 'id' ? 'Rumus' : 'Equation';
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalHeading(app()->getLocale() === 'id' ? 'Sisipkan rumus' : 'Insert equation')
            ->schema([
                Textarea::make('latex')
                    ->label('LaTeX')
                    ->placeholder('E = mc^2')
                    ->helperText(app()->getLocale() === 'id'
                        ? 'Masukkan rumus menggunakan sintaks LaTeX tanpa tanda dolar.'
                        : 'Enter the equation using LaTeX syntax without dollar signs.')
                    ->required()
                    ->maxLength(2000)
                    ->rows(4),
                Select::make('display')
                    ->label(app()->getLocale() === 'id' ? 'Tampilan' : 'Display')
                    ->options([
                        'block' => app()->getLocale() === 'id' ? 'Baris tersendiri' : 'Display block',
                        'inline' => app()->getLocale() === 'id' ? 'Dalam paragraf' : 'Inline',
                    ])
                    ->default('block')
                    ->required(),
            ]);
    }

    public static function getPreviewLabel(array $config): string
    {
        return app()->getLocale() === 'id' ? 'Rumus LaTeX' : 'LaTeX equation';
    }

    public static function toPreviewHtml(array $config): ?string
    {
        return static::equationHtml($config);
    }

    public static function toHtml(array $config, array $data): ?string
    {
        return static::equationHtml($config);
    }

    private static function equationHtml(array $config): string
    {
        $latex = e((string) ($config['latex'] ?? ''));
        $class = ($config['display'] ?? 'block') === 'inline' ? 'equation equation-inline' : 'equation equation-block';

        return '<span class="'.$class.'" data-latex="'.$latex.'"><code>'.$latex.'</code></span>';
    }
}
