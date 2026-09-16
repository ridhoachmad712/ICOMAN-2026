@php
    $s = siteSettings();
    $edition = currentEdition();
    $content = app(\App\Services\SectionContent::class);
    $heading = $section->heading;
    $eyebrow = $section->eyebrow;
    $subheading = $section->subheading;
@endphp

@php $records = $content->records($section); $nextDeadline = $content->nextDeadline(); @endphp

    {{-- IMPORTANT DATES --}}
            <section class="py-16">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :section="$section" :title="$heading" :eyebrow="$eyebrow" :subtitle="$subheading" :center="false" />

                @if($nextDeadline)
                    <div class="mb-6 flex flex-col sm:flex-row items-center justify-between gap-3 rounded-xl bg-gradient-to-r from-[var(--accent)] to-[var(--accent-strong)] text-white px-6 py-4 shadow-lg">
                        <div>
                            <div class="text-xs uppercase tracking-widest text-white/80">{{ __('site.next_deadline') }}</div>
                            <div class="font-semibold">{{ $nextDeadline->label }}</div>
                        </div>
                        <div class="text-lg font-bold">{{ $nextDeadline->date->translatedFormat('d M Y') }}</div>
                    </div>
                @endif

                <ul class="divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white overflow-hidden">
                    @foreach($records as $d)
                        <li class="flex items-center justify-between gap-4 px-5 py-4 {{ $d->is_highlighted ? 'bg-[var(--brand)]/5' : '' }}">
                            <span class="font-medium text-slate-700">{{ $d->label }}</span>
                            <span class="text-sm font-semibold text-[var(--brand-2)] whitespace-nowrap">
                                {{ $d->date?->translatedFormat('d M Y') ?? 'TBA' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
