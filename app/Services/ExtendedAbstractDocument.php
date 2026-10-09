<?php

namespace App\Services;

use App\Models\Submission;
use Illuminate\Support\Facades\Storage;

class ExtendedAbstractDocument
{
    /** @return array<string, array{label: string, html: string, text: string}> */
    public function sections(Submission $submission, bool $embedImages = false): array
    {
        $sections = $submission->extendedAbstractSections();

        if (! $embedImages) {
            return $sections;
        }

        foreach ($sections as &$section) {
            $section['html'] = preg_replace_callback('/<img\b[^>]*\bsrc="([^"]+)"[^>]*>/i', function (array $match) use ($submission): string {
                $path = parse_url(html_entity_decode($match[1]), PHP_URL_PATH);
                $relative = is_string($path) ? rawurldecode(ltrim($path, '/')) : '';
                $prefix = 'storage/submissions/'.$submission->id.'/figures/';

                if (! str_starts_with($relative, $prefix)) {
                    return $match[0];
                }

                $file = substr($relative, strlen('storage/'));
                $disk = Storage::disk('public');
                if (! $disk->exists($file)) {
                    return $match[0];
                }

                $mime = $disk->mimeType($file);
                if (! in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)) {
                    return $match[0];
                }

                $dataUri = 'data:'.$mime.';base64,'.base64_encode($disk->get($file));

                return str_replace($match[1], $dataUri, $match[0]);
            }, $section['html']) ?? $section['html'];
        }
        unset($section);

        return $sections;
    }
}
