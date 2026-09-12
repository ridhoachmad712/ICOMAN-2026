@php
    $s = siteSettings();
    $edition = currentEdition();
    $content = app(\App\Services\SectionContent::class);
    $heading = $section->heading;
    $eyebrow = $section->eyebrow;
    $subheading = $section->subheading;
@endphp

    {{-- ORGANIZED BY --}}
    @if($s->organizer_name || $s->organizer_logo)
        @php $orgLogo = $s->organizer_logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($s->organizer_logo) : null; @endphp
        <section class="py-8 border-b border-slate-100">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-center gap-4 text-center">
                <span class="text-xs uppercase tracking-widest text-slate-400">{{ __('site.home_organized_by') }}</span>
                @if($orgLogo)<img src="{{ $orgLogo }}" alt="{{ $s->organizer_name }}" loading="lazy" class="h-10 w-auto object-contain">@endif
                @if($s->organizer_name)<span class="font-semibold text-[var(--brand-2)]">{{ $s->organizer_name }}</span>@endif
            </div>
        </section>
    @endif
