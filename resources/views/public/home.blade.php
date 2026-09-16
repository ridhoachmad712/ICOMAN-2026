<x-layout :metaDescription="$aboutPage?->meta_description">
    @php
        $s = siteSettings();
        $heroImage = $s->hero_image ? \Illuminate\Support\Facades\Storage::disk('public')->url($s->hero_image) : null;

        // JSON-LD Event (structured data untuk Google).
        $mode = strtolower((string) $s->event_mode);
        $attendance = str_contains($mode, 'hybrid') ? 'MixedEventAttendanceMode'
            : (str_contains($mode, 'online') ? 'OnlineEventAttendanceMode' : 'OfflineEventAttendanceMode');
        $ld = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => ($edition?->name ?? $s->conference_name).($edition?->theme ? ': '.$edition->theme : ''),
            'startDate' => $edition?->start_date?->toIso8601String(),
            'endDate' => ($edition?->end_date ?? $edition?->start_date)?->toIso8601String(),
            'eventAttendanceMode' => 'https://schema.org/'.$attendance,
            'eventStatus' => 'https://schema.org/EventScheduled',
            'url' => route('home'),
            'image' => $heroImage ?: asset('images/hero-pattern.svg'),
            'description' => \Illuminate\Support\Str::limit(strip_tags((string) $aboutPage?->content), 300, ''),
            'location' => $s->event_location ? [
                '@type' => str_contains($mode, 'online') ? 'VirtualLocation' : 'Place',
                'url' => route('venue'),
                'name' => $s->event_location,
                'address' => $s->contact_address ?: $s->event_location,
            ] : null,
            'organizer' => [
                '@type' => 'Organization',
                'name' => $s->organizer_name ?: $s->conference_name,
                'email' => $s->contact_email,
            ],
        ]);
    @endphp

    <x-slot:head>
        <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    </x-slot:head>

    {{-- Susunan beranda datang dari Penyusun Halaman di admin. Selama belum
         disusun sendiri, yang tampil adalah susunan bawaan. --}}
    <x-page-sections target="home" />
</x-layout>
