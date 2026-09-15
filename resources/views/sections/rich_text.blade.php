<section class="py-16 {{ $section->setting('tinted') ? 'section-tint' : 'bg-white' }}">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        @if(filled($section->heading))
            <x-section-heading :section="$section" :title="$section->heading" :eyebrow="$section->eyebrow" :subtitle="$section->subheading" />
        @endif
        <div class="prose prose-slate max-w-none">{!! $section->content !!}</div>
    </div>
</section>
