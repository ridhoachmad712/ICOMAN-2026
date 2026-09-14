@php
    $settings = siteSettings();
    $edition = currentEdition();
    $conferenceName = $edition?->name ?: ($settings->conference_name ?: config('app.name'));

    $logo = $settings->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($settings->logo) : null;

    $eventDates = null;
    if ($edition?->start_date) {
        $eventDates = $edition->start_date->translatedFormat('d M Y');
        if ($edition->end_date && ! $edition->end_date->isSameDay($edition->start_date)) {
            $eventDates .= ' – '.$edition->end_date->translatedFormat('d M Y');
        }
    }

    $facts = array_values(array_filter([$eventDates, $settings->event_location, $settings->event_mode]));
@endphp

{{--
    Rel kiri: sengaja terang dan sepi. Yang ditampilkan hanya identitas acara,
    supaya perhatian tetap jatuh pada formulir di sebelah kanan.
--}}
<aside class="hidden bg-[#f5f6fb] lg:flex lg:flex-col lg:justify-between lg:px-12 lg:py-14">
    <a href="{{ route('home') }}" class="flex flex-col items-center gap-4 text-center">
        @if($logo)
            <img src="{{ $logo }}" alt="{{ $conferenceName }}" class="h-20 w-auto max-w-[14rem] object-contain">
        @else
            <span class="grid h-16 w-16 place-items-center rounded-2xl bg-[var(--brand,#d9621c)] text-lg font-black text-white">IC</span>
        @endif
    </a>

    <div class="max-w-sm">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ __('author.portal') }}</p>
        <h2 class="mt-4 font-display text-2xl font-bold leading-snug text-[var(--brand-2,#18315e)]">{{ $conferenceName }}</h2>
        @if($edition?->theme)
            <p class="mt-3 text-sm leading-relaxed text-slate-500">{{ $edition->theme }}</p>
        @endif

        @if($facts)
            <dl class="mt-8 space-y-4 border-t border-slate-200 pt-6 text-sm">
                @foreach($facts as $fact)
                    <div class="flex items-start gap-3 text-slate-600">
                        <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--brand,#d9621c)]"></span>
                        <span>{{ $fact }}</span>
                    </div>
                @endforeach
            </dl>
        @endif
    </div>

    <p class="text-xs text-slate-400">© {{ date('Y') }} {{ $conferenceName }}</p>
</aside>
