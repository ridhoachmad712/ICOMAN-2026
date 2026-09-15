@php
    $s = siteSettings();
    $edition = currentEdition();
    $content = app(\App\Services\SectionContent::class);
    $heading = $section->heading;
    $eyebrow = $section->eyebrow;
    $subheading = $section->subheading;
@endphp

@php $records = $content->records($section); @endphp

    {{-- CALL FOR PAPERS TEASER --}}
            <section class="py-16">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :section="$section" :title="$heading" :eyebrow="$eyebrow" :subtitle="$subheading" />
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach($records->take(8) as $topic)
                        <div class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3">
                            <span class="mt-1 h-2 w-2 rounded-full bg-[var(--brand)] shrink-0"></span>
                            <span class="text-slate-700">{{ $topic->title }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="text-center mt-8">
                    <a href="{{ route('call-for-papers') }}" class="text-[var(--brand)] font-medium hover:underline">{{ __('site.view_topics') }} →</a>
                </div>
            </div>
        </section>
