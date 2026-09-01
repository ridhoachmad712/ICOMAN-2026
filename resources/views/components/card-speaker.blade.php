@props(['speaker'])

@php
    $photo = $speaker->getFirstMediaUrl('photo', 'card');
    $initials = collect(explode(' ', $speaker->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $flagCode = countryCode($speaker->country);
    $countryLabel = countryName($speaker->country);
@endphp

<div class="group card card-hover overflow-hidden">
    <div class="aspect-square overflow-hidden flex items-center justify-center {{ $photo ? 'bg-slate-100' : 'bg-slate-100 text-slate-400' }}">
        @if($photo)
            <img src="{{ $photo }}" alt="{{ $speaker->name }}" loading="lazy"
                 class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300">
        @else
            <div class="flex flex-col items-center justify-center gap-2 p-4 text-center">
                <div class="h-16 w-16 rounded-full bg-slate-200 text-slate-400 flex items-center justify-center">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                </div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">TBA</span>
            </div>
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
