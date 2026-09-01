<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $submission->title }}</title>
    <style>
        @page { margin: 17mm 19mm 20mm; }
        * { box-sizing: border-box; }
        body {
            color: #151515;
            font-family: "DejaVu Serif", serif;
            font-size: 9.6pt;
            line-height: 1.48;
            margin: 0;
        }
        .page-footer {
            border-top: .45pt solid #9a9a9a;
            bottom: -12mm;
            color: #555;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 7.2pt;
            left: 0;
            padding-top: 2.5mm;
            position: fixed;
            right: 0;
        }
        .page-footer table, .masthead table { border-collapse: collapse; width: 100%; }
        .page-footer td:last-child { text-align: right; }
        .page-number::after { content: counter(page); }
        .masthead {
            border-bottom: 1.4pt solid #d9621c;
            margin-bottom: 8mm;
            padding-bottom: 3.6mm;
        }
        .masthead-logo { vertical-align: middle; width: 54%; }
        .masthead-logo img {
            display: block;
            height: auto;
            max-height: 17mm;
            max-width: 52mm;
        }
        .logo-fallback {
            color: #222;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 20pt;
            font-weight: 700;
            letter-spacing: -.6pt;
        }
        .logo-fallback span { color: #ff451b; }
        .conference-meta {
            color: #333;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 7.2pt;
            line-height: 1.45;
            text-align: right;
            text-transform: uppercase;
            vertical-align: middle;
        }
        .conference-meta strong {
            color: #111;
            display: block;
            font-size: 8.2pt;
            letter-spacing: .25pt;
        }
        .paper-type {
            color: #d9621c;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 7.4pt;
            font-weight: 700;
            letter-spacing: 1.1pt;
            margin-bottom: 3mm;
            text-align: center;
            text-transform: uppercase;
        }
        h1 {
            color: #111;
            font-size: 16.5pt;
            font-weight: 700;
            line-height: 1.24;
            margin: 0 auto 4mm;
            max-width: 165mm;
            text-align: center;
        }
        .authors {
            font-size: 9.3pt;
            line-height: 1.5;
            margin: 0 auto;
            text-align: center;
        }
        .authors .author-name { white-space: nowrap; }
        .affiliations {
            color: #444;
            font-size: 8.2pt;
            font-style: italic;
            line-height: 1.42;
            margin: 2.2mm auto 0;
            text-align: center;
        }
        .correspondence {
            color: #555;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 7.4pt;
            margin-top: 1.7mm;
            text-align: center;
        }
        .paper-meta {
            border-bottom: .45pt solid #b5b5b5;
            border-top: .45pt solid #b5b5b5;
            color: #444;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 7.5pt;
            margin: 6mm 0 5mm;
            padding: 2.2mm 0;
            text-align: center;
        }
        .abstract-block { margin: 0 7mm 4.5mm; }
        .abstract-heading {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9pt;
            font-weight: 700;
            letter-spacing: .5pt;
            margin: 0 0 2mm;
            text-align: center;
            text-transform: uppercase;
        }
        .keywords { font-size: 8.7pt; margin: 3mm 7mm 6mm; }
        .keywords strong { font-style: italic; }
        .paper-section { page-break-inside: auto; }
        .paper-section-title {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10pt;
            font-weight: 700;
            letter-spacing: .35pt;
            margin: 5.5mm 0 2.4mm;
            page-break-after: avoid;
            text-transform: uppercase;
        }
        .paper-content p, .abstract-content p {
            margin: 0 0 2.8mm;
            orphans: 3;
            text-align: justify;
            text-indent: 7mm;
            widows: 3;
        }
        .abstract-content p:first-child, .paper-content > p:first-child { text-indent: 0; }
        .paper-content h2, .paper-content h3, .abstract-content h2, .abstract-content h3 {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9.5pt;
            margin: 4mm 0 2mm;
            page-break-after: avoid;
        }
        .paper-content ul, .paper-content ol, .abstract-content ul, .abstract-content ol {
            margin: 0 0 3mm 7mm;
            padding-left: 5mm;
        }
        .paper-content li, .abstract-content li { margin-bottom: 1.2mm; }
        .paper-content img, .abstract-content img {
            display: block;
            height: auto;
            margin: 4mm auto 2mm;
            max-width: 92%;
            page-break-inside: avoid;
        }
        .paper-content table, .abstract-content table {
            border-collapse: collapse;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 8pt;
            margin: 4mm 0;
            page-break-inside: avoid;
            width: 100%;
        }
        .paper-content th, .abstract-content th { background: #ededed; font-weight: 700; }
        .paper-content td, .paper-content th, .abstract-content td, .abstract-content th {
            border: .5pt solid #777;
            padding: 1.8mm 2mm;
            text-align: left;
            vertical-align: top;
        }
        .paper-content blockquote, .abstract-content blockquote {
            border-left: 1.5pt solid #777;
            color: #444;
            font-style: italic;
            margin: 3mm 7mm;
            padding-left: 3mm;
        }
        .equation {
            display: block;
            font-family: "DejaVu Sans Mono", monospace;
            margin: 3.5mm 0;
            page-break-inside: avoid;
            text-align: center;
        }
        .equation-inline {
            display: inline;
            font-family: "DejaVu Sans Mono", monospace;
            margin: 0 1mm;
        }
        .equation code { background: transparent; padding: 0; }
    </style>
</head>
<body>
    @php
        $documentSections = app(\App\Services\ExtendedAbstractDocument::class)->sections($submission, true);
        $abstractSection = $documentSections['extended_abstract_abstract'] ?? null;
        $bodyFields = [
            'extended_abstract_introduction' => '1. Introduction',
            'extended_abstract_method' => '2. Method',
            'extended_abstract_results_discussion' => '3. Results and Discussion',
            'extended_abstract_conclusion' => '4. Conclusion',
        ];
        $authors = $submission->authors->sortBy('order')->values();
        $affiliations = $authors->pluck('affiliation')->filter()->unique()->values();
        $corresponding = $authors->firstWhere('is_corresponding', true);
        $logoRelativePath = rescue(fn () => siteSettings()->logo, null, false);
        $logoDataUri = null;
        if ($logoRelativePath && \Illuminate\Support\Facades\Storage::disk('public')->exists($logoRelativePath)) {
            $logoMime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($logoRelativePath) ?: 'image/png';
            $logoDataUri = 'data:'.$logoMime.';base64,'.base64_encode(\Illuminate\Support\Facades\Storage::disk('public')->get($logoRelativePath));
        }
        $editionDates = null;
        if ($submission->edition?->start_date) {
            $editionDates = $submission->edition->start_date->format('d M Y');
            if ($submission->edition->end_date && ! $submission->edition->end_date->isSameDay($submission->edition->start_date)) {
                $editionDates .= ' - '.$submission->edition->end_date->format('d M Y');
            }
        }
    @endphp

    <footer class="page-footer">
        <table>
            <tr>
                <td>{{ $submission->edition?->name ?? 'ICOMAN 2026' }} &nbsp;|&nbsp; {{ $submission->submission_number }}</td>
                <td>Page <span class="page-number"></span></td>
            </tr>
        </table>
    </footer>

    <header class="masthead">
        <table>
            <tr>
                <td class="masthead-logo">
                    @if($logoDataUri)
                        <img src="{{ $logoDataUri }}" alt="ICOMAN">
                    @else
                        <div class="logo-fallback"><span>I</span>COMAN</div>
                    @endif
                </td>
                <td class="conference-meta">
                    <strong>{{ $submission->edition?->name ?? 'ICOMAN 2026' }}</strong>
                    International Conference on Management<br>
                    @if($editionDates){{ $editionDates }}<br>@endif
                    Extended Abstract Proceedings
                </td>
            </tr>
        </table>
    </header>

    <main>
        <div class="paper-type">Extended Abstract</div>
        <h1>{{ $submission->title }}</h1>

        <div class="authors">
            @foreach($authors as $author)
                @php $affiliationNumber = $author->affiliation ? $affiliations->search($author->affiliation) + 1 : null; @endphp
                <span class="author-name">{{ $author->name }}@if($affiliationNumber)<sup>{{ $affiliationNumber }}</sup>@endif@if($author->is_corresponding)<sup>*</sup>@endif</span>{{ ! $loop->last ? ', ' : '' }}
            @endforeach
        </div>

        @if($affiliations->isNotEmpty())
            <div class="affiliations">
                @foreach($affiliations as $index => $affiliation)
                    <div><sup>{{ $index + 1 }}</sup>{{ $affiliation }}</div>
                @endforeach
            </div>
        @endif

        @if($corresponding?->email)
            <div class="correspondence">* Corresponding author: {{ $corresponding->email }}</div>
        @endif

        <div class="paper-meta">
            Paper ID: {{ $submission->submission_number }}
            @if($submission->topic) &nbsp;&nbsp;|&nbsp;&nbsp; Track: {{ $submission->topic->title }} @endif
        </div>

        @if($abstractSection && filled($abstractSection['text']))
            <section class="abstract-block">
                <h2 class="abstract-heading">Abstract</h2>
                <div class="abstract-content">{!! $abstractSection['html'] !!}</div>
            </section>
        @endif

        @if(filled($submission->keywords))
            <div class="keywords"><strong>Keywords:</strong> {{ implode('; ', $submission->keywords) }}</div>
        @endif

        @foreach($bodyFields as $field => $label)
            @php $section = $documentSections[$field] ?? null; @endphp
            @continue(! $section || blank($section['text']))
            <section class="paper-section">
                <h2 class="paper-section-title">{{ $label }}</h2>
                <div class="paper-content">{!! $section['html'] !!}</div>
            </section>
        @endforeach
    </main>
</body>
</html>
