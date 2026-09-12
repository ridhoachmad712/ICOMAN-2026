@php $media = $section->getFirstMedia('section'); @endphp

<section class="py-12">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        @if(filled($section->heading))
            <x-section-heading :title="$section->heading" :eyebrow="$section->eyebrow" :subtitle="$section->subheading" />
        @endif
        @if($media)
            <img src="{{ $section->getFirstMediaUrl('section', 'wide') ?: $media->getUrl() }}"
                 alt="{{ $section->heading ?: '' }}" loading="lazy"
                 class="w-full rounded-2xl object-cover shadow-sm">
        @endif
        @if(filled($section->content))
            <p class="mt-3 text-center text-sm text-slate-500">{{ strip_tags($section->content) }}</p>
        @endif
    </div>
</section>
