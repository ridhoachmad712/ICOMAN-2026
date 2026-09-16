@php
    $s = siteSettings();
    $edition = currentEdition();
    $content = app(\App\Services\SectionContent::class);
    $heroImage = $s->hero_image ? \Illuminate\Support\Facades\Storage::disk('public')->url($s->hero_image) : null;
    $heading = $section->heading;
    $eyebrow = $section->eyebrow;
    $subheading = $section->subheading;
@endphp

    {{-- HERO --}}
    <section class="relative bg-[var(--brand-2)] text-white overflow-hidden">
        @if($heroImage)
            <img src="{{ $heroImage }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-40">
            <div class="absolute inset-0 bg-gradient-to-t from-[var(--brand-2)] via-[var(--brand-2)]/80 to-[var(--brand-2)]/40"></div>
        @else
            {{-- Background default: pola SVG self-hosted + wash warna brand --}}
            <img src="{{ asset('images/hero-pattern.svg') }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-br from-[var(--brand)]/25 via-transparent to-[var(--brand-2)]/60"></div>
        @endif
        {{-- Glow duotone (biru + aksen hangat) + vignette untuk kedalaman --}}
        <div aria-hidden="true" class="pointer-events-none absolute -top-32 -right-24 h-[32rem] w-[32rem] rounded-full bg-[var(--brand)] opacity-25 blur-[120px]"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-40 -left-32 h-[34rem] w-[34rem] rounded-full bg-[var(--accent)] opacity-[0.14] blur-[130px]"></div>
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[radial-gradient(120%_120%_at_50%_0%,transparent_50%,rgba(0,0,0,0.4))]"></div>

        {{-- Hero tinggi: konten dipusatkan vertikal agar terasa lapang & formal.
             svh dipakai supaya tinggi tidak melompat saat bar browser mobile muncul/hilang. --}}
        <div class="relative mx-auto flex min-h-[68svh] max-w-7xl flex-col justify-center px-4 py-20 sm:min-h-[76svh] sm:px-6 sm:py-28 lg:px-8">
            {{-- Ketiganya bisa ditimpa lewat Penyusun Halaman; bila dikosongkan,
                 isinya mengikuti data edisi dan Teks Website. --}}
            <p class="eyebrow">
                {{ $section->eyebrow ?: __('site.hero_eyebrow') }}
            </p>
            <h1 class="mt-3 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight max-w-4xl">
                {{ $section->heading ?: ($edition?->name ?: $s->conference_name) }}
            </h1>
            <p class="mt-5 max-w-4xl text-2xl leading-snug font-medium text-white/90 sm:text-3xl lg:text-4xl">
                {{ $section->subheading ?: $edition?->theme }}
            </p>

            {{-- Info chips --}}
            <div class="mt-7 flex flex-wrap items-center gap-2.5 text-sm text-white">
                @if($edition?->start_date)
                    <span class="inline-flex items-center rounded-full bg-white/10 px-3.5 py-1.5 ring-1 ring-white/15 backdrop-blur">
                        {{ $edition->start_date->translatedFormat('d M Y') }}@if($edition->end_date && ! $edition->end_date->equalTo($edition->start_date)) – {{ $edition->end_date->translatedFormat('d M Y') }}@endif
                    </span>
                @endif
                @if($s->event_location && ! str_contains(strtolower((string) $s->event_mode), 'online'))<span class="inline-flex items-center rounded-full bg-white/10 px-3.5 py-1.5 ring-1 ring-white/15 backdrop-blur">{{ $s->event_location }}</span>@endif
                @if($s->event_mode)<span class="inline-flex items-center rounded-full bg-white/10 px-3.5 py-1.5 ring-1 ring-white/15 backdrop-blur">{{ $s->event_mode }}</span>@endif
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('author.register.terms', ['role' => 'presenter']) }}" class="btn btn-accent">{{ __('site.home_submit_abstract') }}</a>
                <a href="{{ route('author.register.terms', ['role' => 'non_presenter']) }}" class="btn btn-ghost">{{ __('site.home_attend_seminar') }}</a>
            </div>
        </div>
    </section>
