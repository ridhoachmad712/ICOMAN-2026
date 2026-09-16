@php
    $records = app(\App\Services\SectionContent::class)->records($section)->groupBy(fn ($item) => optional($item->day_date)->translatedFormat('l, d F Y') ?? '-');
@endphp

<section class="py-16">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <x-section-heading :section="$section" :title="$section->heading" :eyebrow="$section->eyebrow" :subtitle="$section->subheading" />
        @foreach($records as $day => $items)
            <h3 class="mb-3 mt-8 font-display text-lg font-bold text-[var(--brand-2)]">{{ $day }}</h3>
            <div class="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                @foreach($items as $item)
                    <div class="flex flex-col gap-1 px-5 py-4 sm:flex-row sm:items-baseline sm:gap-5">
                        <span class="shrink-0 text-sm font-semibold text-[var(--brand-ink)]">{{ $item->time_start }}@if($item->time_end) – {{ $item->time_end }}@endif</span>
                        <div>
                            <p class="font-medium text-slate-800">{{ $item->title }}</p>
                            @if($item->speaker_name)<p class="mt-0.5 text-sm text-slate-500">{{ $item->speaker_name }}</p>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
        <div class="mt-8 text-center">
            <a href="{{ route('program') }}" class="text-[var(--brand-ink)] font-medium hover:underline">{{ __('site.view_all') }} →</a>
        </div>
    </div>
</section>
