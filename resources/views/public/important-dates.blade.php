<x-layout :title="__('nav.dates')">
    <x-page-header :title="__('site.important_dates')" />

    <section class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-14">
        @if($importantDates->isNotEmpty())
            <ol class="relative border-s-2 border-slate-200 ms-3 space-y-8">
                @foreach($importantDates as $d)
                    <li class="ms-6">
                        <span class="absolute -start-2.5 flex h-5 w-5 items-center justify-center rounded-full {{ $d->is_highlighted ? 'bg-[var(--brand)]' : 'bg-slate-300' }} ring-4 ring-slate-50"></span>
                        <div class="card px-5 py-4 {{ $d->is_highlighted ? 'ring-1 ring-[var(--brand)]/40' : '' }}">
                            <p class="text-sm font-semibold text-[var(--brand-2)]">{{ $d->date?->translatedFormat('l, d F Y') ?? 'TBA' }}</p>
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
