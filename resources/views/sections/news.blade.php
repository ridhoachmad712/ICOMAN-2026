@php
    $s = siteSettings();
    $edition = currentEdition();
    $content = app(\App\Services\SectionContent::class);
    $heading = $section->heading;
    $eyebrow = $section->eyebrow;
    $subheading = $section->subheading;
@endphp

@php $records = $content->records($section); @endphp

    {{-- LATEST NEWS --}}
            <section class="bg-white py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :title="$heading" :eyebrow="$eyebrow" :subtitle="$subheading" />
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($records as $item)
                        <x-card-news :item="$item" />
                    @endforeach
                </div>
            </div>
        </section>
