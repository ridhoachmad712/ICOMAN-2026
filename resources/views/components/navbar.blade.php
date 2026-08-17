@props(['logo' => null, 'name' => 'ICOMAN 2026'])

@php
    $nav = [
        ['type' => 'link', 'route' => 'home', 'label' => __('nav.home')],
        ['type' => 'group', 'label' => __('nav.about'), 'children' => [
            ['route' => 'about', 'label' => __('nav.about')],
            ['route' => 'committee', 'label' => __('nav.committee')],
            ['route' => 'venue', 'label' => __('nav.venue')],
        ]],
        ['type' => 'link', 'route' => 'speakers', 'label' => __('nav.speakers')],
        ['type' => 'group', 'label' => __('nav.program'), 'children' => [
            ['route' => 'call-for-papers', 'label' => __('nav.cfp')],
            ['route' => 'important-dates', 'label' => __('nav.dates')],
            ['route' => 'program', 'label' => __('nav.program')],
        ]],
        ['type' => 'link', 'route' => 'registration', 'label' => __('nav.registration')],
        ['type' => 'link', 'route' => 'news.index', 'label' => __('nav.news')],
        ['type' => 'link', 'route' => 'contact', 'label' => __('nav.contact')],
    ];
    $locale = app()->getLocale();
    $isActive = fn ($r) => request()->routeIs($r);
    $groupActive = fn ($children) => collect($children)->contains(fn ($c) => request()->routeIs($c['route']));
@endphp

<header x-data="{ open: false }" class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b border-slate-200">
    <nav class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2 shrink-0">
                @if($logo)
                    <img src="{{ $logo }}" alt="{{ $name }}" class="h-9 w-auto">
                @else
                    <span class="font-display text-lg font-bold tracking-tight text-[var(--brand-2)]">{{ $name }}</span>
                @endif
            </a>

            {{-- Desktop nav --}}
            <div class="hidden lg:flex items-center gap-1">
                @foreach($nav as $item)
                    @if($item['type'] === 'link')
                        <a href="{{ route($item['route']) }}"
                           class="px-3 py-2 text-sm font-medium rounded-md transition-colors {{ $isActive($item['route']) ? 'text-[var(--brand)]' : 'text-slate-600 hover:text-[var(--brand)] hover:bg-slate-50' }}">
                            {{ $item['label'] }}
                        </a>
                    @else
                        <div x-data="{ o: false }" class="relative" @mouseenter="o = true" @mouseleave="o = false">
                            <button @click="o = !o" :aria-expanded="o" aria-haspopup="true"
                                    class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium rounded-md transition-colors {{ $groupActive($item['children']) ? 'text-[var(--brand)]' : 'text-slate-600 hover:text-[var(--brand)] hover:bg-slate-50' }}">
                                {{ $item['label'] }}
                                <svg class="h-4 w-4 transition-transform" :class="o && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            {{-- top-full + pt-2 = "jembatan" transparan tanpa celah, agar hover tak putus saat kursor turun ke panel --}}
                            <div x-show="o" x-cloak x-transition @click.outside="o = false"
                                 class="absolute left-0 top-full w-48 pt-2">
                                <div class="rounded-lg border border-slate-200 bg-white shadow-lg py-1">
                                    @foreach($item['children'] as $child)
                                        <a href="{{ route($child['route']) }}"
                                           class="block px-4 py-2 text-sm {{ $isActive($child['route']) ? 'text-[var(--brand)] bg-slate-50' : 'text-slate-600 hover:bg-slate-50' }}">
                                            {{ $child['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="flex items-center gap-2">
                {{-- Language switcher --}}
                <div class="hidden sm:flex items-center rounded-md border border-slate-200 overflow-hidden text-xs font-semibold">
                    <a href="{{ route('locale.switch', 'en') }}" class="px-2.5 py-1.5 {{ $locale === 'en' ? 'bg-[var(--brand)] text-white' : 'text-slate-500 hover:bg-slate-50' }}">EN</a>
                    <a href="{{ route('locale.switch', 'id') }}" class="px-2.5 py-1.5 {{ $locale === 'id' ? 'bg-[var(--brand)] text-white' : 'text-slate-500 hover:bg-slate-50' }}">ID</a>
                </div>

                {{-- Mobile toggle --}}
                <button @click="open = !open" :aria-expanded="open" class="lg:hidden inline-flex items-center justify-center p-2 rounded-md text-slate-600 hover:bg-slate-100" aria-label="Menu">
                    <svg x-show="!open" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg x-show="open" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Mobile menu (flattened) --}}
        <div x-show="open" x-cloak x-transition class="lg:hidden pb-4">
            <div class="flex flex-col gap-1">
                @foreach($nav as $item)
                    @if($item['type'] === 'link')
                        <a href="{{ route($item['route']) }}"
                           class="px-3 py-2 text-sm font-medium rounded-md {{ $isActive($item['route']) ? 'bg-[var(--brand)] text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                            {{ $item['label'] }}
                        </a>
                    @else
                        <div class="px-3 pt-3 pb-1 text-xs uppercase tracking-wide text-slate-400">{{ $item['label'] }}</div>
                        @foreach($item['children'] as $child)
                            <a href="{{ route($child['route']) }}"
                               class="px-5 py-2 text-sm rounded-md {{ $isActive($child['route']) ? 'bg-[var(--brand)] text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                                {{ $child['label'] }}
                            </a>
                        @endforeach
                    @endif
                @endforeach

                <div class="flex gap-2 pt-3">
                    <a href="{{ route('locale.switch', 'en') }}" class="flex-1 text-center px-3 py-2 text-sm rounded-md border {{ $locale === 'en' ? 'bg-[var(--brand)] text-white' : 'text-slate-600' }}">English</a>
                    <a href="{{ route('locale.switch', 'id') }}" class="flex-1 text-center px-3 py-2 text-sm rounded-md border {{ $locale === 'id' ? 'bg-[var(--brand)] text-white' : 'text-slate-600' }}">Indonesia</a>
                </div>
            </div>
        </div>
    </nav>
</header>
