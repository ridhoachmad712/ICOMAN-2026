@props([
    'title' => null,
    'metaDescription' => null,
    'ogImage' => null,
    'canonical' => null,
])

@php
    $settings = siteSettings();
    $confName = $settings->conference_name ?: 'ICOMAN 2026';
    $brand = $settings->primary_color ?: '#1d4ed8';
    $brand2 = $settings->secondary_color ?: '#0f172a';
    $pageTitle = $title ? ($title.' — '.$confName) : $confName;
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    $logoUrl = $settings->logo ? $disk->url($settings->logo) : null;
    $faviconUrl = $settings->favicon ? $disk->url($settings->favicon) : null;

    $description = $metaDescription
        ?: (currentEdition()?->getTranslation('theme', app()->getLocale()) ?: 'International Conference on Management');
    $canonicalUrl = $canonical ?: url()->current().'?lang='.app()->getLocale();
    $ogImageUrl = $ogImage
        ?: ($settings->hero_image ? $disk->url($settings->hero_image) : ($logoUrl ?: asset('images/hero-pattern.svg')));
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" style="--brand: {{ $brand }}; --brand-2: {{ $brand2 }};">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>document.documentElement.classList.add('js')</script>
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 160, '') }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="alternate" hreflang="en" href="{{ url()->current() }}?lang=en">
    <link rel="alternate" hreflang="id" href="{{ url()->current() }}?lang=id">
    @if($faviconUrl)<link rel="icon" href="{{ $faviconUrl }}">@endif

    {{-- Open Graph / Twitter --}}
    <meta property="og:site_name" content="{{ $confName }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 200, '') }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $ogImageUrl }}">
    <meta property="og:locale" content="{{ app()->getLocale() === 'id' ? 'id_ID' : 'en_US' }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 200, '') }}">
    <meta name="twitter:image" content="{{ $ogImageUrl }}">

    @php
        // Huruf mengikuti pilihan di Site Settings; hanya nama dari daftar
        // FONTS yang diterima, supaya URL-nya tidak bisa disisipi sembarangan.
        $fonts = \App\Settings\SiteSettings::FONTS;
        $headingFont = array_key_exists((string) $settings->font_heading, $fonts) ? $settings->font_heading : 'Space Grotesk';
        $bodyFont = array_key_exists((string) $settings->font_body, $fonts) ? $settings->font_body : 'Instrument Sans';
        $baseFontSize = ($settings->base_font_size >= 12 && $settings->base_font_size <= 24) ? $settings->base_font_size : 16;
        $fontQuery = collect(array_unique([$bodyFont, $headingFont]))
            ->map(fn (string $family) => 'family='.str_replace(' ', '+', $family).':wght@400;500;600;700')
            ->implode('&');
        $fontHref = 'https://fonts.googleapis.com/css2?'.$fontQuery.'&display=swap';
    @endphp

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="{{ $fontHref }}">
    <link rel="stylesheet" href="{{ $fontHref }}">

    <style>
        :root {
            --font-sans: '{{ $bodyFont }}', ui-sans-serif, system-ui, sans-serif;
            --font-display: '{{ $headingFont }}', '{{ $bodyFont }}', ui-sans-serif, system-ui, sans-serif;
        }
        html { font-size: {{ $baseFontSize }}px; }
    </style>

    {{ $head ?? '' }}

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased flex flex-col">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-[100] focus:top-2 focus:left-2 focus:rounded-md focus:bg-[var(--brand)] focus:px-4 focus:py-2 focus:text-white">
        {{ __('site.layout_skip_to_content') }}
    </a>

    <x-navbar :logo="$logoUrl" :name="$confName" />

    <main id="main-content" class="flex-1">
        {{ $slot }}
    </main>

    <x-footer :name="$confName" />

    @if(canEditPages() && ! pageEditMode())
        {{-- Pintu masuk mode sunting, hanya untuk penyunting yang sedang login. --}}
        <a href="{{ request()->fullUrlWithQuery(['edit' => 1]) }}"
           class="fixed bottom-5 left-5 z-50 inline-flex items-center gap-2 rounded-full bg-[var(--brand-2)] px-4 py-2.5 text-xs font-semibold text-white shadow-lg hover:bg-[var(--brand)]">
            ✎ Sunting halaman
        </a>
    @endif

    <x-floating-language-switcher />

    @livewireScripts
</body>
</html>
