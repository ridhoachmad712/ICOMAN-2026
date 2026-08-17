<x-layout :title="$item->title" :metaDescription="$item->excerpt">
    @php $thumb = $item->getFirstMediaUrl('thumbnail'); @endphp

    <article class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-14">
        <a href="{{ route('news.index') }}" class="text-sm text-[var(--brand)] hover:underline">← {{ __('site.back_to_news') }}</a>

        <h1 class="mt-4 text-3xl sm:text-4xl font-bold tracking-tight text-[var(--brand-2)]">{{ $item->title }}</h1>
        @if($item->published_at)
            <time class="mt-2 block text-sm text-slate-400">{{ $item->published_at->translatedFormat('l, d F Y') }}</time>
        @endif

        @if($thumb)
            <img src="{{ $thumb }}" alt="{{ $item->title }}" class="mt-6 w-full rounded-xl object-cover">
        @endif

        <div class="prose prose-slate max-w-none mt-8">
            {!! $item->content !!}
        </div>
    </article>

    @if($related->isNotEmpty())
        <section class="bg-white py-14">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :title="__('site.related_news')" :center="false" />
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($related as $r)
                        <x-card-news :item="$r" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layout>
