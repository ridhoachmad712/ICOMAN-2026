<x-layout :title="__('nav.news')">
    <x-page-header :title="__('site.latest_news')" />

    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-14">
        @if($news->isNotEmpty())
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($news as $item)
                    <x-card-news :item="$item" />
                @endforeach
            </div>
            <div class="mt-10">
                {{ $news->links() }}
            </div>
        @else
            <x-empty-state />
        @endif
    </section>
</x-layout>
