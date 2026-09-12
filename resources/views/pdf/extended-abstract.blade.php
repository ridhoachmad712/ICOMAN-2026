<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $submission->title }}</title>
    <style>
        /*
         * Lembar abstract satu halaman. Abstract dibatasi 200-250 kata, jadi
         * tata letaknya sengaja lapang: badan teks 10.5pt dan hanya dua
         * keluarga huruf (serif untuk isi, sans untuk label) supaya berkasnya
         * ringan dan mudah dibaca saat dicetak.
         */
        @page { margin: 20mm 22mm 22mm; }

        * { box-sizing: border-box; }

        body {
            color: #151515;
            font-family: "DejaVu Serif", serif;
            font-size: 10.5pt;
            line-height: 1.6;
            margin: 0;
        }

        table { border-collapse: collapse; width: 100%; }

        /* --- Kop & kaki halaman --- */

        .masthead {
            border-bottom: 1.4pt solid #d9621c;
            margin-bottom: 9mm;
            padding-bottom: 4mm;
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
            font-size: 18pt;
            font-weight: 700;
            letter-spacing: -.5pt;
        }

        .conference-meta {
            color: #333;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 7.5pt;
            line-height: 1.5;
            text-align: right;
            text-transform: uppercase;
            vertical-align: middle;
        }

        .conference-meta strong {
            color: #111;
            display: block;
            font-size: 8.5pt;
            letter-spacing: .25pt;
        }

        .page-footer {
            border-top: .45pt solid #9a9a9a;
            bottom: -14mm;
            color: #555;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 7.5pt;
            left: 0;
            padding-top: 2.5mm;
            position: fixed;
            right: 0;
        }

        .page-footer td:last-child { text-align: right; }
        .page-number::after { content: counter(page); }

        /* --- Judul, penulis, afiliasi --- */

        h1 {
            color: #111;
            font-size: 17pt;
            font-weight: 700;
            line-height: 1.26;
            margin: 0 auto 5mm;
            max-width: 160mm;
            text-align: center;
        }

        .authors {
            font-size: 10pt;
            line-height: 1.55;
            margin: 0;
            text-align: center;
        }

        .authors .author-name { white-space: nowrap; }

        .affiliations {
            color: #444;
            font-size: 8.5pt;
            font-style: italic;
            line-height: 1.45;
            margin-top: 2.5mm;
            text-align: center;
        }

        .correspondence {
            color: #555;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 8pt;
            margin-top: 2mm;
            text-align: center;
        }

        /* --- Baris identitas naskah --- */

        .paper-meta {
            border-bottom: .45pt solid #b5b5b5;
            border-top: .45pt solid #b5b5b5;
            color: #444;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 8pt;
            margin: 7mm 0 6mm;
            padding: 2.4mm 0;
            text-align: center;
        }

        .paper-meta .separator { color: #aaa; padding: 0 2.5mm; }

        /* --- Abstract & keywords --- */

        .abstract-block { margin: 0 6mm; }

        .abstract-heading {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9.5pt;
            font-weight: 700;
            letter-spacing: .6pt;
            margin: 0 0 3mm;
            text-align: center;
            text-transform: uppercase;
        }

        .abstract-content p {
            margin: 0 0 3mm;
            orphans: 3;
            text-align: justify;
            text-indent: 8mm;
            widows: 3;
        }

        /* Paragraf pembuka rata kiri tanpa indent, seperti lazimnya jurnal. */
        .abstract-content p:first-child { text-indent: 0; }
        .abstract-content p:last-child { margin-bottom: 0; }

        .keywords {
            border-top: .45pt dotted #b5b5b5;
            font-size: 9.5pt;
            margin-top: 5mm;
            padding-top: 3mm;
            text-align: justify;
        }

        .keywords strong { font-style: italic; }
    </style>
</head>
<body>
    @php
        $settings = rescue(fn () => siteSettings(), null, false);
        $conferenceName = $submission->edition?->name
            ?? $settings?->conference_name
            ?? config('app.name');

        $documentSections = app(\App\Services\ExtendedAbstractDocument::class)->sections($submission);
        $abstractSection = $documentSections['abstract'] ?? null;

        $authors = $submission->authors->sortBy('order')->values();
        $affiliations = $authors->pluck('affiliation')->filter()->unique()->values();
        $corresponding = $authors->firstWhere('is_corresponding', true);

        // Logo disematkan sebagai data URI karena dompdf tidak mengambil berkas
        // lewat HTTP saat render berjalan di latar belakang.
        $logoRelativePath = $settings?->logo;
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

        $metaParts = array_values(array_filter([
            'Paper ID: '.$submission->submission_number,
            $submission->topic?->title ? 'Track: '.$submission->topic->title : null,
            $submission->submitted_at ? 'Submitted: '.$submission->submitted_at->format('d M Y') : null,
        ]));
    @endphp

    <footer class="page-footer">
        <table>
            <tr>
                <td>{{ $conferenceName }} &nbsp;|&nbsp; {{ $submission->submission_number }}</td>
                <td>Page <span class="page-number"></span></td>
            </tr>
        </table>
    </footer>

    <header class="masthead">
        <table>
            <tr>
                <td class="masthead-logo">
                    @if($logoDataUri)
                        <img src="{{ $logoDataUri }}" alt="{{ $conferenceName }}">
                    @else
                        <div class="logo-fallback">{{ $conferenceName }}</div>
                    @endif
                </td>
                <td class="conference-meta">
                    <strong>{{ $conferenceName }}</strong>
                    @if($editionDates){{ $editionDates }}<br>@endif
                    @if($settings?->event_location){{ $settings->event_location }}<br>@endif
                    Abstract Proceedings
                </td>
            </tr>
        </table>
    </header>

    <main>
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
            @foreach($metaParts as $part)
                {{ $part }}@if(! $loop->last)<span class="separator">|</span>@endif
            @endforeach
        </div>

        @if($abstractSection && filled($abstractSection['text']))
            <section class="abstract-block">
                <h2 class="abstract-heading">Abstract</h2>
                <div class="abstract-content">{!! $abstractSection['html'] !!}</div>

                @if(filled($submission->keywords))
                    <div class="keywords"><strong>Keywords:</strong> {{ implode('; ', $submission->keywords) }}</div>
                @endif
            </section>
        @endif
    </main>
</body>
</html>
