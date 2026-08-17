@props(['speaker'])

@php
    $photo = $speaker->getFirstMediaUrl('photo', 'card');
    $initials = collect(explode(' ', $speaker->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $flagCode = countryCode($speaker->country);
    $countryLabel = countryName($speaker->country);
@endphp

<div class="group card card-hover overflow-hidden">
    <div class="aspect-square overflow-hidden flex items-center justify-center {{ $photo ? 'bg-slate-100' : 'avatar-fallback' }}">
        @if($photo)
            <img src="{{ $photo }}" alt="{{ $speaker->name }}" loading="lazy"
                 class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300">
        @else
            <span class="text-4xl font-bold">{{ $initials }}</span>
        @endif
    </div>
    <div class="p-4">
        <div class="flex items-center gap-2">
            <span class="inline-block text-[10px] uppercase tracking-wide font-semibold text-[var(--brand)] bg-[var(--brand)]/10 px-2 py-0.5 rounded">
                {{ ucfirst($speaker->type) }}
            </span>
            @if($countryLabel)
                <span class="inline-flex items-center gap-1.5 text-xs text-slate-500">
                    @if($flagCode)
                        <span class="fi fi-{{ strtolower($flagCode) }} rounded-[2px] ring-1 ring-black/5" title="{{ $countryLabel }}"></span>
                    @endif
                    {{ $countryLabel }}
                </span>
            @endif
        </div>
        <h3 class="mt-2 font-semibold text-slate-900 leading-tight">
            {{ $speaker->title_degree ? $speaker->title_degree.' ' : '' }}{{ $speaker->name }}
        </h3>
        @if($speaker->affiliation)<p class="text-sm text-slate-500">{{ $speaker->affiliation }}</p>@endif
        @if($speaker->topic)<p class="mt-2 text-sm text-slate-600 italic">“{{ $speaker->topic }}”</p>@endif
    </div>
</div>
