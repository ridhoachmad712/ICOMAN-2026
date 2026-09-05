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

    {{-- Fonts: Space Grotesk (display) + Instrument Sans (body) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap">

    {{ $head ?? '' }}

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased flex flex-col">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-[100] focus:top-2 focus:left-2 focus:rounded-md focus:bg-[var(--brand)] focus:px-4 focus:py-2 focus:text-white">
        {{ app()->getLocale() === 'id' ? 'Lompat ke konten' : 'Skip to content' }}
    </a>

    <x-navbar :logo="$logoUrl" :name="$confName" />

    <main id="main-content" class="flex-1">
        {{ $slot }}
    </main>

    <x-footer :name="$confName" />

    <x-floating-language-switcher />

    @livewireScripts
</body>
</html>
