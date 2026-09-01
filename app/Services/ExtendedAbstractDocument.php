<?php

namespace App\Services;

use App\Models\Submission;
use DOMDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExtendedAbstractDocument
{
    /**
     * @return array<string, array{label: string, html: string, text: string}>
     */
    public function sections(Submission $submission, bool $embedImages = false): array
    {
        $sections = $submission->extendedAbstractSections();

        if (! $embedImages) {
            return $sections;
        }

        // Batasi gambar yang boleh di-embed hanya pada attachment milik
        // submission ini sendiri (lihat fileAttachmentsDirectory di
        // EditExtendedAbstract). Tanpa batasan ini, `attrs.id` yang dikontrol
        // author dapat menunjuk file submission lain (baca lintas-author).
        $allowedPrefix = 'submissions/'.$submission->getKey().'/extended-abstract/';

        foreach ($sections as $field => &$section) {
            $section['html'] = $this->embedImages(
                $section['html'],
                $this->imagePaths($submission->getAttribute($field)),
                $allowedPrefix,
            );
        }

        return $sections;
    }

    /**
     * @return array<int, string>
     */
    private function imagePaths(mixed $node): array
    {
        if (! is_array($node)) {
            return [];
        }

        $paths = [];
        if (($node['type'] ?? null) === 'image' && filled($node['attrs']['id'] ?? null)) {
            $paths[] = $node['attrs']['id'];
        }

        foreach ($node['content'] ?? [] as $child) {
            $paths = [...$paths, ...$this->imagePaths($child)];
        }

        return $paths;
    }

    private function embedImages(string $html, array $paths, string $allowedPrefix): string
    {
        if (blank($html) || $paths === []) {
            return $html;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div id="document-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach ($document->getElementsByTagName('img') as $index => $image) {
            $path = $paths[$index] ?? null;
            // Hanya embed file milik submission ini; tolak path di luar prefix
            // atau yang mengandung traversal direktori.
            if (! $path
                || Str::contains($path, '..')
                || ! Str::startsWith($path, $allowedPrefix)
                || ! Storage::disk('local')->exists($path)) {
                continue;
            }

            $mime = Storage::disk('local')->mimeType($path) ?: 'image/png';
            $image->setAttribute('src', 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($path)));
        }

        $root = $document->getElementById('document-root');
        $result = '';
        foreach ($root?->childNodes ?? [] as $child) {
            $result .= $document->saveHTML($child);
        }

        return $result ?: $html;
    }
}
