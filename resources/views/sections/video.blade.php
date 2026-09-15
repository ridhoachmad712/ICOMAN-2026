@php
    // Hanya YouTube dan Vimeo yang diterima, dan URL sematannya dirakit sendiri
    // — supaya alamat sembarangan tidak bisa disematkan ke halaman publik.
    $url = (string) $section->setting('video_url');
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $embed = null;

    if (str_contains($host, 'youtube.com')) {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $id = $query['v'] ?? null;
        $embed = $id ? 'https://www.youtube.com/embed/'.urlencode($id) : null;
    } elseif (str_contains($host, 'youtu.be')) {
        $id = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $embed = $id ? 'https://www.youtube.com/embed/'.urlencode($id) : null;
    } elseif (str_contains($host, 'vimeo.com')) {
        $id = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $embed = preg_match('/^\d+$/', $id) === 1 ? 'https://player.vimeo.com/video/'.$id : null;
    }
@endphp

@if($embed)
    <section class="bg-white py-16">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            @if(filled($section->heading))
                <x-section-heading :section="$section" :title="$section->heading" :eyebrow="$section->eyebrow" :subtitle="$section->subheading" />
            @endif

            <div class="aspect-video overflow-hidden rounded-2xl bg-slate-900">
                <iframe src="{{ $embed }}" title="{{ $section->heading ?: 'Video' }}" loading="lazy"
                        class="h-full w-full" allowfullscreen
                        allow="accelerometer; clipboard-write; encrypted-media; picture-in-picture"></iframe>
            </div>
        </div>
    </section>
@endif
