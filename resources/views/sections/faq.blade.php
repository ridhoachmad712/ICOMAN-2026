@php
    $s = siteSettings();
    $edition = currentEdition();
    $content = app(\App\Services\SectionContent::class);
    $heading = $section->heading;
    $eyebrow = $section->eyebrow;
    $subheading = $section->subheading;
@endphp

@php $records = $content->records($section); @endphp

    {{-- FAQ TEASER --}}
            <section class="section-tint py-16">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :section="$section" :title="$heading" :eyebrow="$eyebrow" :subtitle="$subheading" />
                <div class="space-y-3">
                    @foreach($records as $faq)
                        <div x-data="{ open: false }" class="rounded-lg border border-slate-200 overflow-hidden">
                            <button @click="open = !open" :aria-expanded="open" class="w-full flex items-center justify-between gap-4 px-5 py-4 text-left">
                                <span class="font-medium text-slate-800">{{ $faq->question }}</span>
                                <svg class="h-5 w-5 text-slate-400 transition-transform shrink-0" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="open" x-transition x-cloak class="px-5 pb-4 text-slate-600 whitespace-pre-line">{{ $faq->answer }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="text-center mt-6">
                    <a href="{{ route('faq') }}" class="text-[var(--brand)] font-medium hover:underline">{{ __('site.view_all_faq') }} →</a>
                </div>
            </div>
        </section>
