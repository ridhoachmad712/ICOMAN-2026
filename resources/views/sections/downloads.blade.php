@php
    $records = app(\App\Services\SectionContent::class)->records($section);
@endphp

<section class="bg-white py-16">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <x-section-heading :section="$section" :title="$section->heading" :eyebrow="$section->eyebrow" :subtitle="$section->subheading" />
        <div class="space-y-3">
            @foreach($records as $download)
                <a href="{{ $download->getFirstMediaUrl('file') ?: $download->url }}" target="_blank" rel="noopener"
                   class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 px-5 py-4 transition hover:border-[var(--brand)]">
                    <span class="font-medium text-slate-800">{{ $download->title }}</span>
                    <span class="shrink-0 text-sm font-semibold text-[var(--brand-ink)]">{{ __('site.download') }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>
