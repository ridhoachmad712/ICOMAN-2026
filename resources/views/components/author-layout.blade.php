@props(['title' => null])

@php
    $settings = siteSettings();
    $confName = $settings->conference_name ?: 'ICOMAN 2026';
    $locale = app()->getLocale();
    $brand = $settings->brandColor();
    $brand2 = $settings->brandColor2();
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" class="h-full" style="--brand:{{ $brand }};--brand-2:{{ $brand2 }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' \u00b7 ' : '' }}{{ $confName }} · {{ __('author.portal') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="author-portal min-h-screen bg-[#f4f4f2] text-neutral-950 antialiased font-sans selection:bg-black selection:text-white">
    {{--
        Layout ini kini hanya melayani halaman tamu portal author (register,
        forgot-password, reset-password). Seluruh halaman author yang sudah login
        ditangani panel Filament (AuthorPanelProvider), bukan Blade.
    --}}
    {{--
        Halaman tamu portal author (daftar, syarat & ketentuan). Kerangkanya
        sama persis dengan halaman login Filament: identitas konferensi di
        kiri, isi di kanan.
    --}}
    {{-- Di layar lebar tinggi dikunci satu layar: rel kiri diam, hanya isi di
         kanan yang bergulir. Di ponsel rel kiri disembunyikan, jadi halaman
         bergulir seperti biasa. --}}
    <div class="grid min-h-screen lg:h-screen lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)] lg:overflow-hidden xl:grid-cols-[minmax(0,30rem)_minmax(0,1fr)]">
        <x-author-auth-story />

        <div class="flex min-h-screen flex-col bg-white lg:h-screen lg:min-h-0">
            <header class="flex shrink-0 items-center justify-between px-6 py-5 text-xs text-slate-400 sm:px-10">
                <a href="{{ route('home') }}" class="hover:text-[var(--brand-2)]">← {{ __('author.back_home') }}</a>
                <span class="uppercase tracking-[0.14em]">{{ __('author.portal') }}</span>
            </header>

            <main class="flex-1 px-6 py-12 lg:min-h-0 lg:overflow-y-auto">
                {{-- Panel kiri tidak tampil di ponsel, jadi identitas acara diulang ringkas di sini. --}}
                <p class="mb-6 text-center text-xs font-semibold uppercase tracking-[0.16em] text-[var(--brand-ink)] lg:hidden">
                    {{ currentEdition()?->name ?: $confName }}
                </p>

                @if(session('status'))<div class="author-notice author-notice-dark"><span>✓</span>{{ session('status') }}</div>@endif
                @if(session('error'))<div class="author-notice"><span>!</span>{{ session('error') }}</div>@endif

                {{ $slot }}
            </main>

            <footer class="flex shrink-0 items-center justify-between px-6 py-5 pe-28 text-xs text-slate-400 sm:px-10 sm:pe-32">
                <span class="lg:invisible">© {{ date('Y') }} {{ $confName }}</span>
                @if($settings->contact_email)<a href="mailto:{{ $settings->contact_email }}" class="hover:text-[var(--brand-2)]">{{ __('author.need_help') }} {{ $settings->contact_email }}</a>@endif
            </footer>
        </div>
    </div>

    <x-floating-language-switcher />
    @livewireScripts
</body>
</html>
