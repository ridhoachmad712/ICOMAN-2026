@props(['title', 'subtitle' => null, 'eyebrow' => null])

<section class="relative bg-[var(--brand-2)] text-white overflow-hidden">
    <img src="{{ asset('images/hero-pattern.svg') }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full object-cover opacity-60">
    <div class="absolute inset-0 bg-gradient-to-br from-[var(--brand)]/25 via-transparent to-[var(--brand-2)]/70"></div>
    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-14 sm:py-20">
        @if($eyebrow)
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--brand)] mb-2">{{ $eyebrow }}</p>
        @endif
        <h1 class="font-display text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight">{{ $title }}</h1>
        @if($subtitle)
            <p class="mt-3 text-slate-300 max-w-2xl">{{ $subtitle }}</p>
        @endif
    </div>
</section>
