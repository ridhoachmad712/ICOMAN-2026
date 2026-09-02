@props(['logo' => null, 'name' => 'ICOMAN 2026'])

@php
    $nav = [
        ['type' => 'link', 'route' => 'home', 'label' => __('nav.home')],
        ['type' => 'group', 'label' => __('nav.about'), 'children' => [
            ['route' => 'about', 'label' => __('nav.about')],
            ['route' => 'committee', 'label' => __('nav.committee')],
            ['route' => 'venue', 'label' => __('nav.venue')],
        ]],
        ['type' => 'group', 'label' => __('nav.program'), 'children' => [
            ['route' => 'speakers', 'label' => __('nav.speakers')],
            ['route' => 'call-for-papers', 'label' => __('nav.cfp')],
            ['route' => 'important-dates', 'label' => __('nav.dates')],
            ['route' => 'program', 'label' => __('nav.program')],
        ]],
        ['type' => 'link', 'route' => 'registration', 'label' => __('nav.registration')],
        ['type' => 'link', 'route' => 'news.index', 'label' => __('nav.news')],
        ['type' => 'link', 'route' => 'contact', 'label' => __('nav.contact')],
    ];
    $locale = app()->getLocale();
    $isActive = fn ($route) => request()->routeIs($route);
    $groupActive = fn ($children) => collect($children)->contains(fn ($child) => request()->routeIs($child['route']));
@endphp

<header x-data="{ open: false }" class="sticky top-0 z-50 border-b border-slate-200 bg-white/95 backdrop-blur">
    <nav class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" aria-label="{{ $locale === 'id' ? 'Navigasi utama' : 'Main navigation' }}">
        <div class="flex h-[4.5rem] items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5">
                @if($logo)
                    <img src="{{ $logo }}" alt="{{ $name }}" class="h-10 w-auto">
                @else
                    @php $monogram = \Illuminate\Support\Str::of($name)->explode(' ')->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''); @endphp
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-[var(--brand)] to-[var(--brand-2)] font-display text-sm font-bold text-white shadow-sm">{{ strtoupper($monogram) ?: 'IC' }}</span>
                    <span class="font-display text-lg font-bold leading-none tracking-tight text-[var(--brand-2)]">{{ $name }}</span>
                @endif
            </a>

            <div class="hidden items-center gap-0.5 xl:flex">
                @foreach($nav as $item)
                    @if($item['type'] === 'link')
                        <a href="{{ route($item['route']) }}" class="rounded-md px-3 py-2 text-sm font-medium transition-colors {{ $isActive($item['route']) ? 'bg-slate-100 text-[var(--brand)]' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950' }}">
                            {{ $item['label'] }}
                        </a>
                    @else
                        <div x-data="{ expanded: false }" class="relative" @mouseenter="expanded = true" @mouseleave="expanded = false">
                            <button @click="expanded = !expanded" :aria-expanded="expanded" aria-haspopup="true" class="inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium transition-colors {{ $groupActive($item['children']) ? 'bg-slate-100 text-[var(--brand)]' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950' }}">
                                {{ $item['label'] }}
                                <svg class="h-4 w-4 transition-transform" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/></svg>
                            </button>
                            <div x-show="expanded" x-cloak x-transition @click.outside="expanded = false" class="absolute left-0 top-full w-56 pt-2">
                                <div class="rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg shadow-slate-900/10">
                                    @foreach($item['children'] as $child)
                                        <a href="{{ route($child['route']) }}" class="block rounded-lg px-3 py-2.5 text-sm {{ $isActive($child['route']) ? 'bg-slate-100 font-semibold text-[var(--brand)]' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950' }}">{{ $child['label'] }}</a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="flex items-center gap-2">
                @guest('author')
                    <a href="{{ route('filament.author.auth.login') }}" class="hidden text-sm font-medium text-slate-600 hover:text-slate-950 xl:inline-flex">{{ __('author.login') }}</a>
                @endguest

                @auth('author')
                    <a href="{{ route('filament.author.pages.author-dashboard') }}" class="btn btn-accent hidden min-h-10 px-4 py-2 text-xs sm:inline-flex">Dashboard</a>
                @else
                    <a href="{{ route('author.register') }}" class="btn btn-accent hidden min-h-10 px-4 py-2 text-xs sm:inline-flex">{{ $locale === 'id' ? 'Daftar Sekarang' : 'Register Now' }}</a>
                @endauth

                <button @click="open = !open" :aria-expanded="open" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-md text-slate-700 hover:bg-slate-100 xl:hidden" aria-label="Menu">
                    <svg x-show="!open" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg x-show="open" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <div x-show="open" x-cloak x-transition class="border-t border-slate-100 pb-5 pt-3 xl:hidden">
            <div class="flex flex-col gap-1">
                @foreach($nav as $item)
                    @if($item['type'] === 'link')
                        <a href="{{ route($item['route']) }}" class="rounded-lg px-3 py-2.5 text-sm font-medium {{ $isActive($item['route']) ? 'bg-slate-100 text-[var(--brand)]' : 'text-slate-700 hover:bg-slate-50' }}">{{ $item['label'] }}</a>
                    @else
                        <p class="px-3 pb-1 pt-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">{{ $item['label'] }}</p>
                        @foreach($item['children'] as $child)
                            <a href="{{ route($child['route']) }}" class="rounded-lg px-5 py-2.5 text-sm {{ $isActive($child['route']) ? 'bg-slate-100 font-semibold text-[var(--brand)]' : 'text-slate-700 hover:bg-slate-50' }}">{{ $child['label'] }}</a>
                        @endforeach
                    @endif
                @endforeach

                @auth('author')
                    <div class="mt-3 border-t border-slate-100 pt-4">
                        <a href="{{ route('filament.author.pages.author-dashboard') }}" class="btn btn-primary w-full">Dashboard</a>
                    </div>
                @else
                    <div class="mt-3 grid gap-2 border-t border-slate-100 pt-4 sm:grid-cols-2">
                        <a href="{{ route('author.register') }}" class="btn btn-accent min-h-11 px-4 text-sm">{{ $locale === 'id' ? 'Daftar Sekarang' : 'Register Now' }}</a>
                        <a href="{{ route('filament.author.auth.login') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 px-4 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ __('author.login') }}</a>
                    </div>
                @endauth
            </div>
        </div>
    </nav>
</header>
