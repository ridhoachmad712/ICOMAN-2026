@php
    $locale = app()->getLocale();
    $stats = collect($section->setting('stats', []));
@endphp

<section class="section-tint py-14">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if(filled($section->heading))
            <x-section-heading :section="$section" :title="$section->heading" :eyebrow="$section->eyebrow" :subtitle="$section->subheading" />
        @endif

        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($stats as $stat)
                @php $label = $stat['label_'.$locale] ?? $stat['label_id'] ?? $stat['label_en'] ?? null; @endphp
                <div class="text-center">
                    <p class="font-display text-4xl font-bold text-[var(--brand-ink)]">{{ $stat['value'] ?? '' }}</p>
                    @if(filled($label))<p class="mt-2 text-sm text-slate-600">{{ $label }}</p>@endif
                </div>
            @endforeach
        </div>
    </div>
</section>
