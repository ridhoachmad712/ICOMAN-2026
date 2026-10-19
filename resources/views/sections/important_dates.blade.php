@php
    $s = siteSettings();
    $edition = currentEdition();
    $content = app(\App\Services\SectionContent::class);
    $heading = $section->heading;
    $eyebrow = $section->eyebrow;
    $subheading = $section->subheading;
@endphp

@php $records = $content->records($section); $nextDeadline = $content->nextDeadline(); @endphp

    {{-- IMPORTANT DATES --}}
    {{-- Garis waktu: yang sudah lewat diredupkan, tenggat berikutnya satu-
         satunya yang diberi warna merek, sisanya biru tua. Dengan begitu
         pengunjung langsung melihat posisi "sekarang" tanpa membaca semua. --}}
    <section class="section-y bg-white">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <x-section-heading :section="$section" :title="$heading" :eyebrow="$eyebrow" :subtitle="$subheading" :center="false" />

            <ol data-reveal class="relative space-y-1 before:absolute before:bottom-4 before:left-[0.6875rem] before:top-4 before:w-px before:bg-slate-200">
                @foreach($records as $d)
                    @php
                        $deadline = $d->deadlineAt();
                        $isPast = $deadline?->isPast() ?? false;
                        $isNext = $nextDeadline && $nextDeadline->is($d);
                    @endphp
                    <li class="relative flex gap-4 rounded-xl py-3 pr-4 {{ $isNext ? 'bg-[var(--brand)]/8 pl-0 ring-1 ring-[var(--brand)]/25' : '' }}">
                        <span aria-hidden="true" @class([
                            'relative z-10 mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full ring-4 ring-white',
                            'bg-slate-200 text-slate-500' => $isPast,
                            'bg-[var(--brand)] text-white' => $isNext,
                            'bg-[var(--brand-2)] text-white' => ! $isPast && ! $isNext,
                        ])>
                            @if($isPast)
                                <x-ui-icon name="check" class="h-3.5 w-3.5" />
                            @else
                                <span class="h-2 w-2 rounded-full bg-white"></span>
                            @endif
                        </span>
                        <div class="flex min-w-0 flex-1 flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                            <div>
                                @if($isNext)<p class="text-sm font-semibold text-[var(--brand-ink)]">{{ __('site.next_deadline') }}</p>@endif
                                <p class="font-medium {{ $isPast ? 'text-slate-500' : 'text-slate-800' }}">{{ $d->label }}</p>
                            </div>
                            <p class="shrink-0 text-sm sm:text-right">
                                <span class="font-semibold {{ $isPast ? 'text-slate-500' : 'text-[var(--brand-2)]' }}">{{ $deadline?->translatedFormat('d M Y') ?? __('site.deadline_to_be_announced') }}</span>
                                @if($deadline)
                                    <span class="ml-2 text-slate-500 sm:ml-0 sm:block">{{ $isPast ? __('site.public_date_passed') : __('site.public_date_upcoming') }}</span>
                                @endif
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
