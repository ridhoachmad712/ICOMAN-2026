@php
    $s = siteSettings();
    $edition = currentEdition();
    $content = app(\App\Services\SectionContent::class);
    $page = $content->page($section);
    $heading = $section->heading ?: $page?->title;
    $split = $section->setting('layout') === 'split';

    // Ringkasan bersih: rapikan spasi, buang tema yang sudah tampil di hero,
    // lalu ambil paragraf pertama saja.
    $excerpt = \Illuminate\Support\Str::limit(
        trim(strip_tags(explode('</p>', (string) $page?->content)[0])),
        (int) $section->setting('excerpt_length', 420),
    );
@endphp

@if($split)
    <section class="section-y mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-2 lg:items-center">
            <div data-reveal>
                <x-section-heading :section="$section" :title="$heading" :eyebrow="$section->eyebrow ?: __('nav.about')" :center="false" />
                <p class="text-base leading-relaxed text-slate-600">{{ $excerpt }}</p>
                @if($page)
                    <a href="{{ route('page', ['slug' => $page->slug]) }}" class="link-more mt-6">{{ __('site.learn_more') }}</a>
                @endif
            </div>

            <div data-reveal class="card p-8">
                <div class="space-y-1">
                    @php
                        $factRows = array_filter([
                            // Tanggal sudah tampil di panel fakta hero; di sini cukup
                            // rincian tempat dan format pelaksanaan.
                            $s->event_location ? ['map-pin', __('site.home_location'), $s->event_location] : null,
                            $s->event_mode ? ['monitor', 'Format', $s->event_mode] : null,
                        ]);
                    @endphp
                    @foreach($factRows as [$icon, $label, $value])
                        <div class="flex items-start gap-4 py-3 {{ ! $loop->last ? 'border-b border-slate-100' : '' }}">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[var(--brand)]/10 text-[var(--brand-ink)]">
                                <x-ui-icon :name="$icon" class="h-5 w-5" />
                            </span>
                            <div>
                                <div class="text-sm text-slate-500">{{ $label }}</div>
                                <div class="mt-0.5 font-semibold text-[var(--brand-2)]">{{ $value }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
@elseif($section->setting('layout') === 'features')
    {{-- Pita biru tua dengan butir daftar sebagai kartu. Dipakai untuk luaran
         publikasi: bukti yang paling dicari calon pemakalah. --}}
    <section class="on-dark section-band section-y">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <x-section-heading :section="$section" :title="$heading" :eyebrow="$section->eyebrow" :subtitle="$section->subheading" :center="false" dark />
            <div data-reveal class="prose prose-invert prose-features max-w-none text-white/80">
                {!! $page?->content !!}
            </div>
        </div>
    </section>
@else
    <section class="bg-white py-16">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <x-section-heading :section="$section" :title="$heading" :eyebrow="$section->eyebrow" :subtitle="$section->subheading" :center="false" />
            <div class="prose prose-slate max-w-none">
                {!! $page?->content !!}
            </div>
        </div>
    </section>
@endif
