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
            <section class="section-tint section-y">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :section="$section" :title="$heading" :eyebrow="$eyebrow" :subtitle="$subheading" />
                {{-- Berita sedikit tidak dibiarkan menempel di kiri dengan ruang kosong
                     di sebelahnya: lebar kisinya mengikuti jumlah berita. --}}
                <div @class([
                    'grid gap-6',
                    'mx-auto max-w-md' => $records->count() === 1,
                    'mx-auto max-w-4xl sm:grid-cols-2' => $records->count() === 2,
                    'sm:grid-cols-2 lg:grid-cols-3' => $records->count() > 2,
                ])>
                    @foreach($records as $item)
                        <x-card-news :item="$item" />
                    @endforeach
                </div>
            </div>
        </section>
