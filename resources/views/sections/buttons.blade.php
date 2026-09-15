@php
    $locale = app()->getLocale();
    $buttons = collect($section->setting('buttons', []))->filter(fn ($b) => filled($b['url'] ?? null));
@endphp

<section class="bg-white py-12">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if(filled($section->heading))
            <x-section-heading :title="$section->heading" :eyebrow="$section->eyebrow" :subtitle="$section->subheading" />
        @endif

        <div class="flex flex-wrap items-center justify-center gap-4">
            @foreach($buttons as $button)
                @php $label = $button['label_'.$locale] ?? $button['label_id'] ?? $button['label_en'] ?? null; @endphp
                @continue(blank($label))
                <a href="{{ $button['url'] }}"
                   @if($button['new_tab'] ?? false) target="_blank" rel="noopener" @endif
                   class="btn {{ ($button['style'] ?? 'primary') === 'outline' ? 'btn-outline' : (($button['style'] ?? '') === 'accent' ? 'btn-accent' : 'btn-primary') }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>
</section>
