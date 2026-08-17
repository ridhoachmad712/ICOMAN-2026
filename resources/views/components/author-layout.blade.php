@props(['title' => null])

@php
    $settings = siteSettings();
    $confName = $settings->conference_name ?: 'ICOMAN 2026';
    $brand = $settings->primary_color ?: '#1d4ed8';
    $brand2 = $settings->secondary_color ?: '#0f172a';
    $author = auth('author')->user();
    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" style="--brand: {{ $brand }}; --brand-2: {{ $brand2 }};">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' — ' : '' }}{{ $confName }} · {{ __('author.portal') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased flex flex-col">
    <header class="bg-[var(--brand-2)] text-white">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            <a href="{{ $author ? route('author.dashboard') : route('home') }}" class="flex items-center gap-2 font-display font-bold">
                {{ $confName }} <span class="text-xs font-normal text-white/60">{{ __('author.portal') }}</span>
            </a>
            <div class="flex items-center gap-4 text-sm">
                <div class="hidden sm:flex items-center rounded-md border border-white/20 overflow-hidden text-xs font-semibold">
                    <a href="{{ route('locale.switch', 'en') }}" class="px-2.5 py-1.5 {{ $locale === 'en' ? 'bg-[var(--brand)] text-white' : 'text-white/60 hover:bg-white/10' }}">EN</a>
                    <a href="{{ route('locale.switch', 'id') }}" class="px-2.5 py-1.5 {{ $locale === 'id' ? 'bg-[var(--brand)] text-white' : 'text-white/60 hover:bg-white/10' }}">ID</a>
                </div>
                <a href="{{ route('home') }}" class="text-white/70 hover:text-white">← {{ __('author.back_home') }}</a>
                @if($author)
                    <span class="text-white/70 hidden sm:inline">{{ $author->name }}</span>
                    <form method="POST" action="{{ route('author.logout') }}">
                        @csrf
                        <button class="rounded-md bg-white/10 px-3 py-1.5 hover:bg-white/20">{{ __('author.logout') }}</button>
                    </form>
                @endif
            </div>
        </div>
    </header>

    <main class="flex-1">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-10">
            @if(session('status'))
                <div class="mb-6 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-emerald-700 text-sm">
                    {{ session('status') }}
                </div>
            @endif
            {{ $slot }}
        </div>
    </main>

    @livewireScripts
</body>
</html>
