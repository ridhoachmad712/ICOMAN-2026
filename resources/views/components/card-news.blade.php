@props(['item'])

@php
    $thumb = $item->getFirstMediaUrl('thumbnail', 'card');
    $displayTitle = str_ireplace(['Full Paper', 'full paper'], ['Abstract', 'abstract'], (string) $item->title);
    $displayExcerpt = str_ireplace(['Full Paper', 'full paper'], ['Abstract', 'abstract'], (string) $item->excerpt);
@endphp

<article class="group card card-hover overflow-hidden flex flex-col">
    <a href="{{ route('news.show', $item->slug) }}" class="block aspect-video bg-slate-100 overflow-hidden">
        @if($thumb)
            <img src="{{ $thumb }}" alt="{{ $displayTitle }}" loading="lazy"
                 class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300">
        @else
            <div class="h-full w-full flex items-center justify-center avatar-fallback">
                <svg class="h-10 w-10 opacity-40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M18 8.25h.008v.008H18V8.25z"/></svg>
            </div>
        @endif
    </a>
    <div class="p-5 flex flex-col flex-1">
        @if($item->published_at)
            <time class="text-xs text-slate-400">{{ $item->published_at->translatedFormat('d M Y') }}</time>
        @endif
        <h3 class="mt-1 font-semibold text-slate-900 leading-snug">
            <a href="{{ route('news.show', $item->slug) }}" class="hover:text-[var(--brand)]">{{ $displayTitle }}</a>
        </h3>
        @if($displayExcerpt)<p class="mt-2 text-sm text-slate-500 line-clamp-3">{{ $displayExcerpt }}</p>@endif
        <a href="{{ route('news.show', $item->slug) }}" class="mt-3 inline-block text-sm font-medium text-[var(--brand)] hover:underline">
            {{ __('site.read_more') }} →
        </a>
    </div>
</article>
