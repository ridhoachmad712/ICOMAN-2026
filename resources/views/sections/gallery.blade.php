@php
    $s = siteSettings();
    $edition = currentEdition();
    $content = app(\App\Services\SectionContent::class);
    $heading = $section->heading;
    $eyebrow = $section->eyebrow;
    $subheading = $section->subheading;
@endphp

@php $records = $content->records($section); @endphp

    {{-- GALLERY HIGHLIGHT --}}
            <section class="py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :section="$section" :title="$heading" :eyebrow="$eyebrow" :subtitle="$subheading" />
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    @foreach($records as $g)
                        @php $img = $g->getFirstMediaUrl('image', 'thumb'); @endphp
                        <div class="aspect-square rounded-lg overflow-hidden bg-slate-100">
                            @if($img)
                                <img src="{{ $img }}" alt="{{ $g->caption ?? '' }}" loading="lazy" class="h-full w-full object-cover hover:scale-105 transition-transform duration-300">
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
