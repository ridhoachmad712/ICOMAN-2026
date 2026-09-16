@php
    $coHosts = \App\Models\CoHost::query()
        ->approved()
        ->when(currentEdition(), fn ($query, $edition) => $query->where('edition_id', $edition->id))
        ->with('media')
        ->orderBy('institution_name')
        ->get()
        // Hanya kemitraan yang sudah berjalan penuh yang tampil di publik.
        ->filter(fn (\App\Models\CoHost $coHost) => $coHost->isActive());
@endphp

<section class="bg-white py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-section-heading :section="$section" :title="$section->heading ?: __('site.cohosts_title')" :eyebrow="$section->eyebrow" :subtitle="$section->subheading" />

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($coHosts as $coHost)
                @php $logo = $coHost->getFirstMediaUrl('logo', 'thumb'); @endphp
                <div class="card flex items-center gap-4 p-5">
                    @if($logo)
                        <img src="{{ $logo }}" alt="{{ $coHost->institution_name }}" loading="lazy" class="h-14 w-14 shrink-0 object-contain">
                    @endif
                    <div class="min-w-0">
                        <p class="font-semibold text-[var(--brand-2)]">{{ $coHost->institution_name }}</p>
                        @if($coHost->typeLabel())<p class="mt-0.5 text-xs text-slate-500">{{ $coHost->typeLabel() }}</p>@endif
                        @if($coHost->website)
                            <a href="{{ $coHost->website }}" target="_blank" rel="noopener" class="mt-1 inline-block text-xs font-medium text-[var(--brand)] hover:underline">
                                {{ parse_url($coHost->website, PHP_URL_HOST) }}
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
