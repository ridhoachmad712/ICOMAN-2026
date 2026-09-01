@props(['title', 'subtitle' => null, 'eyebrow' => null, 'center' => true])

<div data-reveal class="{{ $center ? 'text-center max-w-2xl mx-auto' : 'max-w-2xl' }} mb-10">
    @if($eyebrow)
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--brand)] mb-2">{{ $eyebrow }}</p>
    @endif
    <h2 class="font-display text-3xl font-bold text-[var(--brand-2)] sm:text-4xl">{{ $title }}</h2>
    @if($subtitle)
        <p class="mt-3 text-slate-500">{{ $subtitle }}</p>
    @endif
    <div class="mt-4 h-1 w-16 rounded-full bg-gradient-to-r from-[var(--brand)] to-[var(--brand-2)] {{ $center ? 'mx-auto' : '' }}"></div>
</div>
