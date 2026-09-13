@php
    $settings = siteSettings();
    $edition = currentEdition();
    $conferenceName = $edition?->name ?: ($settings->conference_name ?: config('app.name'));

    $logo = $settings->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($settings->logo) : null;
    $heroImage = $settings->hero_image ? \Illuminate\Support\Facades\Storage::disk('public')->url($settings->hero_image) : null;

    // Tenggat abstrak yang masih berjalan saja: hitung mundur ke tanggal lewat
    // hanya membuat cemas tanpa guna.
    $abstractDeadline = app(\App\Services\ConferenceDeadlines::class)->date('abstract');
    $showCountdown = $abstractDeadline && $abstractDeadline->isFuture();

    $eventDates = null;
    if ($edition?->start_date) {
        $eventDates = $edition->start_date->translatedFormat('d M Y');
        if ($edition->end_date && ! $edition->end_date->isSameDay($edition->start_date)) {
            $eventDates .= ' – '.$edition->end_date->translatedFormat('d M Y');
        }
    }

    $chips = array_values(array_filter([$eventDates, $settings->event_location, $settings->event_mode]));
@endphp

<aside class="relative hidden overflow-hidden bg-[var(--brand-2,#18315e)] text-white lg:flex lg:flex-col lg:justify-between lg:p-12">
    @if($heroImage)
        <img src="{{ $heroImage }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full object-cover opacity-25">
    @endif
    {{-- Lapisan gelap menjaga teks tetap terbaca berapa pun terangnya gambar hero. --}}
    <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-br from-[var(--brand-2,#18315e)]/95 via-[var(--brand-2,#18315e)]/85 to-[var(--brand,#d9621c)]/60"></div>

    <a href="{{ route('home') }}" class="relative flex items-center gap-3 text-sm font-semibold">
        @if($logo)
            <img src="{{ $logo }}" alt="{{ $conferenceName }}" class="h-10 w-auto max-w-[9rem] object-contain">
        @else
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-[var(--brand,#d9621c)] text-xs font-black">IC</span>
        @endif
        <span class="truncate">{{ $conferenceName }}</span>
    </a>

    <div class="relative max-w-md">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/70">{{ __('author.portal') }}</p>
        <h2 class="mt-4 font-display text-3xl font-bold leading-tight xl:text-4xl">{{ __('site.portal_headline') }}</h2>
        <p class="mt-4 text-sm leading-relaxed text-white/80">{{ __('site.portal_subheadline') }}</p>

        @if($chips)
            <div class="mt-7 flex flex-wrap gap-2 text-xs text-white/90">
                @foreach($chips as $chip)
                    <span class="rounded-full bg-white/10 px-3 py-1.5 ring-1 ring-white/15 backdrop-blur">{{ $chip }}</span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="relative">
        @if($showCountdown)
            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.14em] text-white/70">
                {{ __('site.auth_abstract_closes_in') }}
            </p>
            <x-countdown :date="$abstractDeadline" />
        @else
            <p class="text-xs text-white/60">© {{ date('Y') }} {{ $conferenceName }}</p>
        @endif
    </div>
</aside>
