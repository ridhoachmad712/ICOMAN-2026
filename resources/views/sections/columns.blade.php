@php
    $locale = app()->getLocale();
    $columns = collect($section->setting('columns', []))->filter(fn ($column) => filled($column));
    $count = max(1, min(4, $columns->count()));
@endphp

<section class="bg-white py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if(filled($section->heading))
            <x-section-heading :section="$section" :title="$section->heading" :eyebrow="$section->eyebrow" :subtitle="$section->subheading" />
        @endif

        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-{{ $count }}">
            @foreach($columns as $column)
                @php
                    // Tiap kolom menyimpan teksnya per bahasa; jatuh ke bahasa
                    // satunya bila salah satu belum diisi.
                    $text = $column['text_'.$locale] ?? $column['text_id'] ?? $column['text_en'] ?? null;
                    $image = $column['image'] ?? null;
                    $label = $column['button_label'] ?? null;
                    $url = $column['button_url'] ?? null;
                @endphp

                <div class="flex flex-col">
                    @if($image)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image) }}"
                             alt="" loading="lazy" class="mb-5 w-full rounded-xl object-cover">
                    @endif

                    @if(filled($text))
                        <div class="prose prose-slate max-w-none">{!! $text !!}</div>
                    @endif

                    @if(filled($label) && filled($url))
                        <a href="{{ $url }}" class="btn btn-primary mt-5 inline-flex self-start">{{ $label }}</a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
