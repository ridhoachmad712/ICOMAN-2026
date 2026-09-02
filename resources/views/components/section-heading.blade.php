@props(['title', 'subtitle' => null, 'eyebrow' => null, 'center' => true])

<div data-reveal class="{{ $center ? 'text-center mx-auto' : '' }} mb-12 max-w-2xl">
    @if($eyebrow)
        <p class="eyebrow {{ $center ? 'justify-center' : '' }} mb-3">{{ $eyebrow }}</p>
    @endif
    <h2 class="font-display text-3xl font-bold tracking-tight text-[var(--brand-2)] sm:text-4xl">{{ $title }}</h2>
    @if($subtitle)
        <p class="mt-4 text-base leading-relaxed text-slate-500">{{ $subtitle }}</p>
    @endif
    @unless($eyebrow)
        <div class="mt-5 h-1 w-16 rounded-full bg-gradient-to-r from-[var(--accent)] to-[var(--brand)] {{ $center ? 'mx-auto' : '' }}"></div>
    @endunless
</div>
