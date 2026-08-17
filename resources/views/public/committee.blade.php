<x-layout :title="__('nav.committee')">
    <x-page-header :title="__('site.our_committee')" />

    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-14 space-y-14">
        @forelse($committees as $category => $members)
            <div>
                <h2 class="font-display text-2xl font-bold text-[var(--brand-2)] mb-6">
                    {{ __('site.committee_categories.'.$category) }}
                </h2>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($members as $m)
                        @php $photo = $m->getFirstMediaUrl('photo', 'thumb'); @endphp
                        <div class="card card-hover p-5 text-center">
                            <div class="mx-auto h-24 w-24 rounded-full overflow-hidden flex items-center justify-center {{ $photo ? 'bg-slate-100' : 'avatar-fallback' }}">
                                @if($photo)
                                    <img src="{{ $photo }}" alt="{{ $m->name }}" loading="lazy" class="h-full w-full object-cover">
                                @else
                                    <span class="text-2xl font-bold">{{ mb_substr($m->name, 0, 1) }}</span>
                                @endif
                            </div>
                            <h3 class="mt-3 font-semibold text-slate-900">{{ $m->name }}</h3>
                            @if($m->role_title)<p class="text-sm text-[var(--brand)]">{{ $m->role_title }}</p>@endif
                            @if($m->affiliation)<p class="text-xs text-slate-500 mt-1">{{ $m->affiliation }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <x-empty-state />
        @endforelse
    </section>
</x-layout>
