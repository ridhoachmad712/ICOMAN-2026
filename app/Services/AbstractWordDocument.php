<?php

namespace App\Services;

use App\Models\Submission;
use RuntimeException;
use ZipArchive;

/**
 * Abstrak yang diterima dalam format Word (.docx), untuk panitia yang
 * menyusun book of abstracts. Isi dan urutannya mengikuti PDF abstrak.
 *
 * Disusun langsung dengan ZipArchive — .docx hanyalah zip berisi XML — supaya
 * tidak perlu paket tambahan.
 */
class AbstractWordDocument
{
    /** Membuat berkas .docx sementara untuk satu abstrak dan mengembalikan path-nya. */
    public function create(Submission $submission): string
    {
        return $this->write([$submission]);
    }

    /**
     * Banyak abstrak dalam satu berkas, satu abstrak per halaman: bahan awal
     * book of abstracts. Urutannya mengikuti koleksi yang diberikan.
     *
     * @param  iterable<Submission>  $submissions
     */
    public function createCombined(iterable $submissions): string
    {
        return $this->write($submissions);
    }

    public function combinedFileName(): string
    {
        return 'abstrak-accepted-'.now()->format('Ymd-His').'.docx';
    }

    /** @param  iterable<Submission>  $submissions */
    private function write(iterable $submissions): string
    {
        $path = tempnam(sys_get_temp_dir(), 'abstract-');
        $zip = new ZipArchive;

        if ($path === false || $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Berkas Word sementara tidak dapat dibuat.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->packageRelationships());
        $zip->addFromString('word/_rels/document.xml.rels', $this->documentRelationships());
        $zip->addFromString('word/styles.xml', $this->styles());
        $zip->addFromString('word/document.xml', $this->document($submissions));
        $zip->close();

        return $path;
    }

    public function fileName(Submission $submission): string
    {
        return $submission->submission_number.'-abstract.docx';
    }

    /** @param  iterable<Submission>  $submissions */
    private function document(iterable $submissions): string
    {
        $pages = [];
        foreach ($submissions as $submission) {
            $submission->loadMissing(['edition', 'topic', 'authors']);
            $pages[] = implode('', $this->body($submission));
        }

        // Pemisah halaman di antara abstrak, bukan sesudah yang terakhir.
        $pageBreak = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'.implode($pageBreak, $pages)
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
            .'<w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="720" w:footer="720" w:gutter="0"/></w:sectPr>'
            .'</w:body></w:document>';
    }

    /** @return list<string> paragraf-paragraf satu abstrak */
    private function body(Submission $submission): array
    {
        $settings = rescue(fn () => siteSettings(), null, false);
        $conferenceName = $submission->edition?->name ?? $settings?->conference_name ?? config('app.name');

        $authors = $submission->authors->sortBy('order')->values();
        $affiliations = $authors->pluck('affiliation')->filter()->unique()->values();
        $corresponding = $authors->firstWhere('is_corresponding', true);

        $edition = $submission->edition;
        $dates = $edition?->start_date?->format('d M Y');
        if ($dates && $edition->end_date && ! $edition->end_date->isSameDay($edition->start_date)) {
            $dates .= ' - '.$edition->end_date->format('d M Y');
        }

        $body = [];
        $body[] = $this->paragraph([$this->run($conferenceName, bold: true, size: 20)], align: 'center', after: 0);
        $subline = implode(' | ', array_filter([$dates, $settings?->event_location]));
        if ($subline !== '') {
            $body[] = $this->paragraph([$this->run($subline, size: 18)], align: 'center', after: 0);
        }
        $body[] = $this->paragraph([$this->run('Abstract', italic: true, size: 18)], align: 'center', after: 360);

        $body[] = $this->paragraph([$this->run((string) $submission->title, bold: true, size: 28)], align: 'center', after: 200);

        $authorRuns = [];
        foreach ($authors as $index => $author) {
            $authorRuns[] = $this->run($author->name);
            $marks = array_filter([
                $author->affiliation ? (string) ($affiliations->search($author->affiliation) + 1) : null,
                $author->is_corresponding ? '*' : null,
            ]);
            if ($marks) {
                $authorRuns[] = $this->run(implode(',', $marks), superscript: true);
            }
            if ($index < $authors->count() - 1) {
                $authorRuns[] = $this->run(', ');
            }
        }
        if ($authorRuns) {
            $body[] = $this->paragraph($authorRuns, align: 'center', after: 120);
        }

        foreach ($affiliations as $index => $affiliation) {
            $body[] = $this->paragraph([
                $this->run((string) ($index + 1), superscript: true, size: 20),
                $this->run($affiliation, size: 20),
            ], align: 'center', after: 0);
        }

        if ($corresponding?->email) {
            $body[] = $this->paragraph([$this->run('* Corresponding author: '.$corresponding->email, italic: true, size: 20)], align: 'center', before: 120, after: 0);
        }

        $meta = implode(' | ', array_filter([
            'Paper ID: '.$submission->submission_number,
            $submission->topic?->title ? 'Track: '.$submission->topic->title : null,
        ]));
        $body[] = $this->paragraph([$this->run($meta, size: 18)], align: 'center', before: 120, after: 360);

        $body[] = $this->paragraph([$this->run('Abstract', bold: true)], after: 120);

        $paragraphs = preg_split('/\R{2,}/', trim((string) $submission->abstract)) ?: [];
        foreach (array_filter(array_map('trim', $paragraphs)) as $paragraph) {
            $body[] = $this->paragraph([$this->run(preg_replace('/\s*\R\s*/', ' ', $paragraph))], align: 'both', after: 160);
        }

        if (filled($submission->keywords)) {
            $body[] = $this->paragraph([
                $this->run('Keywords: ', bold: true),
                $this->run(implode('; ', $submission->keywords)),
            ], before: 120);
        }

        return $body;
    }

    /** @param  list<string>  $runs */
    private function paragraph(array $runs, string $align = 'left', int $before = 0, int $after = 120): string
    {
        return '<w:p><w:pPr><w:spacing w:before="'.$before.'" w:after="'.$after.'"/><w:jc w:val="'.$align.'"/></w:pPr>'
            .implode('', $runs).'</w:p>';
    }

    /** Ukuran dalam setengah poin (24 = 12pt). */
    private function run(string $text, bool $bold = false, bool $italic = false, bool $superscript = false, int $size = 24): string
    {
        $properties = ($bold ? '<w:b/>' : '').($italic ? '<w:i/>' : '')
            .($superscript ? '<w:vertAlign w:val="superscript"/>' : '')
            .'<w:sz w:val="'.$size.'"/><w:szCs w:val="'.$size.'"/>';

        return '<w:r><w:rPr>'.$properties.'</w:rPr><w:t xml:space="preserve">'
            .htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</w:t></w:r>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'</Types>';
    }

    private function packageRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';
    }

    private function documentRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:docDefaults><w:rPrDefault><w:rPr>'
            .'<w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="Times New Roman" w:cs="Times New Roman"/>'
            .'<w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/>'
            .'</w:rPr></w:rPrDefault>'
            .'<w:pPrDefault><w:pPr><w:spacing w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault>'
            .'</w:docDefaults>'
            .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
            .'</w:styles>';
    }
}
