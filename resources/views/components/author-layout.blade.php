@props(['title' => null])

@php
    $settings = siteSettings();
    $confName = $settings->conference_name ?: 'ICOMAN 2026';
    $locale = app()->getLocale();
    $brand = $settings->primary_color ?: '#d9621c';
    $brand2 = $settings->secondary_color ?: '#18315e';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" class="h-full" style="--brand:{{ $brand }};--brand-2:{{ $brand2 }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' — ' : '' }}{{ $confName }} · {{ __('author.portal') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="author-portal min-h-screen bg-[#f4f4f2] text-neutral-950 antialiased font-sans selection:bg-black selection:text-white">
    {{--
        Layout ini kini hanya melayani halaman tamu portal author (register,
        forgot-password, reset-password). Seluruh halaman author yang sudah login
        ditangani panel Filament (AuthorPanelProvider), bukan Blade.
    --}}
    <div class="author-auth-shell">
        <aside class="author-auth-story">
            <a href="{{ route('home') }}" class="author-wordmark author-wordmark-light"><span>IC</span><strong>{{ $confName }}</strong></a>
            <div class="author-auth-copy">
                <span class="author-kicker">Participant & Author Portal</span>
                <h1>{{ app()->getLocale() === 'id' ? 'Kelola keikutsertaan Anda dengan mudah.' : 'Manage your participation with ease.' }}</h1>
                <p>{{ app()->getLocale() === 'id' ? 'Input abstrak, pantau hasil review, dan selesaikan registrasi konferensi melalui satu portal.' : 'Enter your abstract, follow the review result, and complete conference registration in one portal.' }}</p>
            </div>
            <div class="author-auth-steps" aria-label="Conference workflow">
                <span>1. Submit</span><span>2. Review</span><span>3. Register</span><span>4. Present</span>
            </div>
        </aside>
        <div class="author-auth-panel">
            <header class="author-auth-topbar">
                <a href="{{ route('home') }}">← {{ __('author.back_home') }}</a>
                <span>{{ __('author.portal') }}</span>
            </header>
            <main class="author-auth-main">
                @if(session('status'))<div class="author-notice author-notice-dark"><span>✓</span>{{ session('status') }}</div>@endif
                @if(session('error'))<div class="author-notice"><span>!</span>{{ session('error') }}</div>@endif
                {{ $slot }}
            </main>
            <footer class="author-auth-footer">
                <span>© {{ date('Y') }} {{ $confName }}</span>
                @if($settings->contact_email)<a href="mailto:{{ $settings->contact_email }}">{{ __('author.need_help') }}</a>@endif
            </footer>
        </div>
    </div>

    <x-floating-language-switcher />
    @livewireScripts
</body>
</html>
