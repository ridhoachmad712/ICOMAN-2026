@php
    $locale = app()->getLocale();
    $cards = collect($section->setting('cards', []));
@endphp

<section class="bg-white py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if(filled($section->heading))
            <x-section-heading :section="$section" :title="$section->heading" :eyebrow="$section->eyebrow" :subtitle="$section->subheading" />
        @endif

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($cards as $card)
                @php
                    $title = $card['title_'.$locale] ?? $card['title_id'] ?? $card['title_en'] ?? null;
                    $text = $card['text_'.$locale] ?? $card['text_id'] ?? $card['text_en'] ?? null;
                @endphp
                <div class="card p-6">
                    @if(filled($card['icon'] ?? null))
                        <span class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-[var(--brand)]/10 text-[var(--brand)]">
                            <x-ui-icon :name="$card['icon']" class="h-6 w-6" />
                        </span>
                    @endif
                    @if(filled($title))<h3 class="font-display text-lg font-bold text-[var(--brand-2)]">{{ $title }}</h3>@endif
                    @if(filled($text))<p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $text }}</p>@endif
                </div>
            @endforeach
        </div>
    </div>
</section>
