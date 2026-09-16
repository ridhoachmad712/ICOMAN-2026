@php
    $records = app(\App\Services\SectionContent::class)->records($section);
@endphp

<section class="bg-white py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-section-heading :section="$section" :title="$section->heading" :eyebrow="$section->eyebrow" :subtitle="$section->subheading" />
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($records as $member)
                <div class="card p-5 text-center">
                    @if($member->getFirstMediaUrl('photo'))
                        <img src="{{ $member->getFirstMediaUrl('photo') }}" alt="{{ $member->name }}" loading="lazy"
                             class="mx-auto mb-3 h-24 w-24 rounded-full object-cover">
                    @endif
                    <p class="font-semibold text-[var(--brand-2)]">{{ $member->name }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $member->role_title }}</p>
                    @if($member->affiliation)<p class="mt-1 text-xs text-slate-400">{{ $member->affiliation }}</p>@endif
                </div>
            @endforeach
        </div>
        <div class="mt-8 text-center">
            <a href="{{ route('committee') }}" class="link-more">{{ __('site.view_all') }} →</a>
        </div>
    </div>
</section>
