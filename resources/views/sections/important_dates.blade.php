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
                    <div class="mb-6 flex flex-col sm:flex-row items-center justify-between gap-3 rounded-xl bg-[var(--brand-ink)] text-white px-6 py-4">
                        <div>
                            <div class="text-xs font-semibold text-white/80">{{ __('site.next_deadline') }}</div>
                            <div class="font-semibold">{{ $nextDeadline->label }}</div>
                        </div>
                        <div class="text-lg font-bold">{{ $nextDeadline->deadlineAt()->translatedFormat('d M Y') }}</div>
                    </div>
                @endif

                <ul class="divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white overflow-hidden">
                    @foreach($records as $d)
                        @php $deadline = $d->deadlineAt(); @endphp
                        <li class="flex items-start justify-between gap-4 px-5 py-4 {{ $d->is_highlighted ? 'bg-[var(--brand)]/5' : '' }}">
                            <span class="font-medium text-slate-700">{{ $d->label }}</span>
                            <span class="text-right text-sm font-semibold text-[var(--brand-2)] whitespace-nowrap">
                                {{ $deadline?->translatedFormat('d M Y') ?? __('site.deadline_to_be_announced') }}
                                @if($deadline)<small class="mt-1 block font-medium {{ $deadline->isPast() ? 'text-slate-500' : 'text-[var(--brand-ink)]' }}">{{ $deadline->isPast() ? __('site.public_date_passed') : __('site.public_date_upcoming') }}</small>@endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
