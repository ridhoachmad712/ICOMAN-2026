@php
    $content = app(\App\Services\SectionContent::class);
    $importantDates = $content->records($section);
@endphp

    <p class="mx-auto mt-6 max-w-3xl px-4 text-sm text-slate-600">{{ __('site.dates_all_deadlines_use_central_indonesia') }}</p>
    <section class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-14">
        @if($importantDates->isNotEmpty())
            <ol class="relative border-s-2 border-slate-200 ms-3 space-y-8">
                @foreach($importantDates as $d)
                    @php $deadline = $d->deadlineAt(); @endphp
                    <li class="ms-6">
                        <span class="absolute -start-2.5 flex h-5 w-5 items-center justify-center rounded-full {{ $d->is_highlighted ? 'bg-[var(--brand-ink)]' : 'bg-slate-300' }} ring-4 ring-slate-50"></span>
                        <div class="card px-5 py-4 {{ $d->is_highlighted ? 'ring-1 ring-[var(--brand)]/40' : '' }}">
                            <p class="text-sm font-semibold text-[var(--brand-2)]">{{ $deadline?->translatedFormat('l, d F Y H:i') ?? __('site.dates_to_be_announced') }}</p>
                            <p class="text-slate-600">{{ $d->label }}</p>
                            @if($deadline)<p class="mt-2 text-xs font-semibold {{ $deadline->isPast() ? 'text-slate-500' : 'text-[var(--brand-ink)]' }}">{{ $deadline->isPast() ? __('site.public_date_passed') : __('site.public_date_upcoming') }}</p>@endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @else
            <x-empty-state />
        @endif
    </section>
