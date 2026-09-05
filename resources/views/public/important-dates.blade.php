<x-layout :title="__('nav.dates')">
    <x-page-header :title="__('site.important_dates')" />

    <p class="mx-auto mt-6 max-w-3xl px-4 text-sm text-slate-600">{{ app()->getLocale() === 'id' ? 'Semua tenggat dalam WITA (UTC+8). Jika jam tidak dicantumkan, tenggat berakhir pukul 23:59.' : 'All deadlines use Central Indonesia Time (UTC+8). Where no time is stated, the deadline ends at 23:59.' }}</p>
    <section class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-14">
        @if($importantDates->isNotEmpty())
            <ol class="relative border-s-2 border-slate-200 ms-3 space-y-8">
                @foreach($importantDates as $d)
                    <li class="ms-6">
                        <span class="absolute -start-2.5 flex h-5 w-5 items-center justify-center rounded-full {{ $d->is_highlighted ? 'bg-[var(--brand)]' : 'bg-slate-300' }} ring-4 ring-slate-50"></span>
                        <div class="card px-5 py-4 {{ $d->is_highlighted ? 'ring-1 ring-[var(--brand)]/40' : '' }}">
                            <p class="text-sm font-semibold text-[var(--brand-2)]">{{ $d->closes_at?->format('d M Y H:i') ?? $d->date?->translatedFormat('l, d F Y') ?? (app()->getLocale() === 'id' ? 'Akan diumumkan' : 'To be announced') }}</p>
                            <p class="text-slate-600">{{ $d->label }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        @else
            <x-empty-state />
        @endif
    </section>
</x-layout>
