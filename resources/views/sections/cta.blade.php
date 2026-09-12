@php
    $url = $section->setting('button_url');
    $label = $section->setting('button_label');
@endphp

<section class="py-16 {{ $section->setting('tinted', true) ? 'section-tint' : 'bg-white' }}">
    <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
        @if(filled($section->eyebrow))
            <p class="eyebrow text-[var(--brand)]">{{ $section->eyebrow }}</p>
        @endif
        @if(filled($section->heading))
            <h2 class="mt-2 font-display text-2xl font-bold text-[var(--brand-2)] sm:text-3xl">{{ $section->heading }}</h2>
        @endif
        @if(filled($section->subheading))
            <p class="mx-auto mt-3 max-w-2xl text-slate-600">{{ $section->subheading }}</p>
        @endif
        @if(filled($url) && filled($label))
            <a href="{{ $url }}" class="btn btn-primary mt-6 inline-flex">{{ $label }}</a>
        @endif
    </div>
</section>
