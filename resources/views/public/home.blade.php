<x-layout :metaDescription="$aboutPage?->meta_description">
    @php
        $s = siteSettings();
        $isId = app()->getLocale() === 'id';
        $heroImage = $s->hero_image ? \Illuminate\Support\Facades\Storage::disk('public')->url($s->hero_image) : null;
        $tierOrder = ['platinum' => 'Platinum', 'gold' => 'Gold', 'silver' => 'Silver', 'partner' => 'Partner', 'media_partner' => 'Media Partner'];
        $deadlineLabel = fn ($label) => str_ireplace(
            ['Abstract & Full Paper Submission Deadline', 'Batas Pengumpulan Abstrak & Full Paper', 'Camera-Ready Paper & Registration Payment Deadline', 'Batas Pengumpulan Camera-Ready & Pembayaran'],
            ['Abstract Submission Deadline', 'Batas Pengumpulan Abstrak', 'Abstract & Registration Payment Deadline', 'Batas Input Abstract & Pembayaran'],
            (string) $label,
        );

        // JSON-LD Event (structured data untuk Google).
        $mode = strtolower((string) $s->event_mode);
        $attendance = str_contains($mode, 'hybrid') ? 'MixedEventAttendanceMode'
            : (str_contains($mode, 'online') ? 'OnlineEventAttendanceMode' : 'OfflineEventAttendanceMode');
        $ld = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => ($edition?->name ?? 'ICOMAN 2026').($edition?->theme ? ' — '.$edition->theme : ''),
            'startDate' => $edition?->start_date?->toIso8601String(),
            'endDate' => ($edition?->end_date ?? $edition?->start_date)?->toIso8601String(),
            'eventAttendanceMode' => 'https://schema.org/'.$attendance,
            'eventStatus' => 'https://schema.org/EventScheduled',
            'url' => route('home'),
            'image' => $heroImage ?: asset('images/hero-pattern.svg'),
            'description' => \Illuminate\Support\Str::limit(strip_tags((string) $aboutPage?->content), 300, ''),
            'location' => $s->event_location ? [
                '@type' => 'Place',
                'name' => $s->event_location,
                'address' => $s->contact_address ?: $s->event_location,
            ] : null,
            'organizer' => [
                '@type' => 'Organization',
                'name' => $s->conference_name ?: 'ICOMAN 2026',
                'email' => $s->contact_email,
            ],
        ]);
    @endphp

    <x-slot:head>
        <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    </x-slot:head>

    {{-- HERO --}}
    <section class="relative bg-[var(--brand-2)] text-white overflow-hidden">
        @if($heroImage)
            <img src="{{ $heroImage }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-40">
            <div class="absolute inset-0 bg-gradient-to-t from-[var(--brand-2)] via-[var(--brand-2)]/80 to-[var(--brand-2)]/40"></div>
        @else
            {{-- Background default: pola SVG self-hosted + wash warna brand --}}
            <img src="{{ asset('images/hero-pattern.svg') }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-br from-[var(--brand)]/25 via-transparent to-[var(--brand-2)]/60"></div>
        @endif
        {{-- Glow duotone (biru + aksen hangat) + vignette untuk kedalaman --}}
        <div aria-hidden="true" class="pointer-events-none absolute -top-32 -right-24 h-[32rem] w-[32rem] rounded-full bg-[var(--brand)] opacity-25 blur-[120px]"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-40 -left-32 h-[34rem] w-[34rem] rounded-full bg-[var(--accent)] opacity-[0.14] blur-[130px]"></div>
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[radial-gradient(120%_120%_at_50%_0%,transparent_50%,rgba(0,0,0,0.4))]"></div>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-20 sm:py-28">
            <p class="eyebrow">
                {{ $edition?->name ?? 'ICOMAN 2026' }}
            </p>
            <h1 class="mt-3 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight max-w-4xl">
                {{ $edition?->theme ?: 'International Conference on Management' }}
            </h1>

            {{-- Info chips --}}
            <div class="mt-7 flex flex-wrap items-center gap-2.5 text-sm text-white">
                @if($edition?->start_date)
                    <span class="inline-flex items-center rounded-full bg-white/10 px-3.5 py-1.5 ring-1 ring-white/15 backdrop-blur">
                        {{ $edition->start_date->translatedFormat('d M Y') }}@if($edition->end_date && ! $edition->end_date->equalTo($edition->start_date)) – {{ $edition->end_date->translatedFormat('d M Y') }}@endif
                    </span>
                @endif
                @if($s->event_location)<span class="inline-flex items-center rounded-full bg-white/10 px-3.5 py-1.5 ring-1 ring-white/15 backdrop-blur">{{ $s->event_location }}</span>@endif
                @if($s->event_mode)<span class="inline-flex items-center rounded-full bg-white/10 px-3.5 py-1.5 ring-1 ring-white/15 backdrop-blur">{{ $s->event_mode }}</span>@endif
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('registration') }}" class="btn btn-accent">{{ $isId ? 'Registrasi' : 'Register' }}</a>
                <a href="{{ route('about') }}" class="btn btn-ghost">{{ $isId ? 'Tentang ICOMAN' : 'About ICOMAN' }}</a>
            </div>
        </div>
    </section>

    {{-- STATS STRIP (banner, mengangkat dari hero) --}}
    @php
        $statItems = collect([['speakers','speakers_count'],['topics','topics_count'],['countries','countries_count']])
            ->filter(fn ($item) => (int) ($stats[$item[0]] ?? 0) > 0)
            ->values();
    @endphp
    @if($statItems->isNotEmpty())
        <section class="relative z-10 -mt-10">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div data-reveal class="grid divide-white/15 rounded-2xl bg-gradient-to-r from-[var(--brand)] to-[var(--brand-2)] py-7 text-white shadow-[0_24px_60px_-24px_rgba(15,23,42,0.55)] sm:divide-x" style="grid-template-columns: repeat({{ $statItems->count() }}, minmax(0, 1fr));">
                    @foreach($statItems as [$key,$label])
                        <div class="px-2 text-center">
                            <div class="font-display text-4xl font-bold tabular-nums sm:text-5xl">{{ $stats[$key] }}</div>
                            <div class="mt-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-white/75">{{ __('site.'.$label) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ORGANIZED BY --}}
    @if($s->organizer_name || $s->organizer_logo)
        @php $orgLogo = $s->organizer_logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($s->organizer_logo) : null; @endphp
        <section class="py-8 border-b border-slate-100">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-center gap-4 text-center">
                <span class="text-xs uppercase tracking-widest text-slate-400">{{ app()->getLocale() === 'id' ? 'Diselenggarakan oleh' : 'Organized by' }}</span>
                @if($orgLogo)<img src="{{ $orgLogo }}" alt="{{ $s->organizer_name }}" loading="lazy" class="h-10 w-auto object-contain">@endif
                @if($s->organizer_name)<span class="font-semibold text-[var(--brand-2)]">{{ $s->organizer_name }}</span>@endif
            </div>
        </section>
    @endif

    {{-- ABOUT SUMMARY (2 kolom) --}}
    @if($aboutPage)
        <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-20">
            <div class="grid gap-10 lg:grid-cols-2 lg:items-center">
                <div data-reveal>
                    <x-section-heading :title="$aboutPage->title" :eyebrow="__('nav.about')" :center="false" />
                    @php
                        // Ringkasan bersih untuk homepage: rapikan spasi, buang tema yang
                        // sudah tampil di hero, dan potong sebelum bagian bernomor gaya proposal.
                        $aboutText = trim(preg_replace('/\s+/', ' ', strip_tags($aboutPage->content)));
                        if ($edition?->theme && \Illuminate\Support\Str::startsWith($aboutText, $edition->theme)) {
                            $aboutText = trim(substr($aboutText, strlen($edition->theme)));
                        }
                        $aboutExcerpt = trim(preg_split('/\s+\d+[\.\)]\s/', $aboutText)[0]);
                    @endphp
                    <p class="text-base leading-relaxed text-slate-600">{{ $aboutExcerpt }}</p>
                    <a href="{{ route('about') }}" class="mt-6 inline-block text-[var(--brand)] font-medium hover:underline">{{ __('site.learn_more') }} →</a>
                </div>
                <div data-reveal class="card p-8">
                    <div class="space-y-1">
                        @php
                            $factRows = array_filter([
                                $edition?->start_date ? ['calendar', (app()->getLocale() === 'id' ? 'Tanggal' : 'Date'), $edition->start_date->translatedFormat('d M Y').($edition->end_date && ! $edition->end_date->equalTo($edition->start_date) ? ' – '.$edition->end_date->translatedFormat('d M Y') : '')] : null,
                                $s->event_location ? ['map-pin', (app()->getLocale() === 'id' ? 'Lokasi' : 'Location'), $s->event_location] : null,
                                $s->event_mode ? ['monitor', 'Format', $s->event_mode] : null,
                            ]);
                        @endphp
                        @foreach($factRows as [$icon, $label, $value])
                            <div class="flex items-start gap-4 py-3 {{ ! $loop->last ? 'border-b border-slate-100' : '' }}">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[var(--brand)]/10 text-[var(--brand)]">
                                    <x-ui-icon :name="$icon" class="h-5 w-5" />
                                </span>
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $label }}</div>
                                    <div class="mt-0.5 font-semibold text-[var(--brand-2)]">{{ $value }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <a href="{{ route('registration') }}" class="btn btn-primary mt-6 w-full">{{ __('site.register_now') }}</a>
                </div>
            </div>
        </section>
    @endif

    {{-- SPEAKERS --}}
    @php
        // Speaker "asli" = yang namanya bukan placeholder TBA. Kalau belum ada,
        // tampilkan state "To Be Announced" yang ringkas.
        $announcedSpeakers = $speakers->reject(fn ($sp) => str_contains(strtolower((string) $sp->name), 'tba'));
    @endphp
    <section class="bg-white py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-section-heading :title="__('site.keynote_speakers')" />

            @if($announcedSpeakers->isEmpty())
                <div data-reveal class="mx-auto max-w-md text-center">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-[var(--brand)]/10 text-[var(--brand)]">
                        <x-ui-icon name="users" class="h-6 w-6" />
                    </div>
                    <p class="text-lg font-semibold text-[var(--brand-2)]">{{ app()->getLocale() === 'id' ? 'Segera Diumumkan' : 'To Be Announced' }}</p>
                </div>
            @else
                @php
                    $spotlight = $announcedSpeakers->firstWhere('type', 'keynote') ?? $announcedSpeakers->first();
                    $gridSpeakers = $announcedSpeakers->reject(fn ($sp) => $spotlight && $sp->id === $spotlight->id);
                @endphp

                {{-- Keynote spotlight --}}
                @if($spotlight)
                    @php
                        $spPhoto = $spotlight->getFirstMediaUrl('photo', 'card');
                        $spFlag = countryCode($spotlight->country);
                    @endphp
                    <div class="mb-10 grid gap-6 sm:grid-cols-3 items-center card p-6 sm:p-8">
                        <div class="aspect-square sm:aspect-auto sm:h-56 rounded-xl bg-slate-200 overflow-hidden flex items-center justify-center">
                            @if($spPhoto)
                                <img src="{{ $spPhoto }}" alt="{{ $spotlight->name }}" class="h-full w-full object-cover">
                            @else
                                <span class="text-5xl font-bold text-slate-300">{{ mb_substr($spotlight->name, 0, 1) }}</span>
                            @endif
                        </div>
                        <div class="sm:col-span-2">
                            <span class="inline-block text-[10px] uppercase tracking-widest font-semibold text-[var(--brand)] bg-[var(--brand)]/10 px-2 py-0.5 rounded">{{ ucfirst($spotlight->type) }}</span>
                            <h3 class="mt-2 text-2xl font-bold text-[var(--brand-2)]">
                                {{ $spotlight->title_degree ? $spotlight->title_degree.' ' : '' }}{{ $spotlight->name }}
                            </h3>
                            <p class="text-slate-500 inline-flex items-center gap-2">
                                @if($spFlag)<span class="fi fi-{{ strtolower($spFlag) }} rounded-[2px] ring-1 ring-black/5"></span>@endif
                                {{ $spotlight->affiliation }}
                            </p>
                            @if($spotlight->topic)<p class="mt-3 text-slate-700 font-medium">“{{ $spotlight->topic }}”</p>@endif
                            @if($spotlight->bio)<p class="mt-2 text-sm text-slate-500 line-clamp-3">{{ strip_tags($spotlight->bio) }}</p>@endif
                        </div>
                    </div>
                @endif

                @if($gridSpeakers->isNotEmpty())
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach($gridSpeakers as $speaker)
                            <x-card-speaker :speaker="$speaker" />
                        @endforeach
                    </div>
                @endif

                <div class="text-center mt-8">
                    <a href="{{ route('speakers') }}" class="text-[var(--brand)] font-medium hover:underline">{{ __('site.view_all') }} →</a>
                </div>
            @endif
        </div>
    </section>

    {{-- CALL FOR PAPERS TEASER --}}
    @if($topics->isNotEmpty())
        <section class="py-16">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :title="__('site.call_for_papers')" :subtitle="__('site.topics')" />
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach($topics->take(8) as $topic)
                        <div class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3">
                            <span class="mt-1 h-2 w-2 rounded-full bg-[var(--brand)] shrink-0"></span>
                            <span class="text-slate-700">{{ $topic->title }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="text-center mt-8">
                    <a href="{{ route('call-for-papers') }}" class="text-[var(--brand)] font-medium hover:underline">{{ __('site.view_topics') }} →</a>
                </div>
            </div>
        </section>
    @endif

    {{-- REGISTRATION TEASER (pricing) --}}
    @if($fees->isNotEmpty())
        <section class="section-tint py-20">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :title="__('site.registration_fees')" :eyebrow="app()->getLocale() === 'id' ? 'Investasi' : 'Investment'" />
                @php
                    $audiences = [
                        'presenter' => app()->getLocale() === 'id' ? 'Presenter (Pemakalah)' : 'Presenter',
                        'participant' => app()->getLocale() === 'id' ? 'Peserta Seminar' : 'Seminar Attendee',
                    ];
                @endphp
                <div class="space-y-10">
                    @foreach($audiences as $aud => $audLabel)
                        @php $group = $fees->where('audience', $aud)->sortBy('order'); @endphp
                        @if($group->isNotEmpty())
                            <div data-reveal>
                                <h3 class="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-[var(--brand-2)]">
                                    <span class="h-4 w-1 rounded-full bg-[var(--brand)]"></span>{{ $audLabel }}
                                </h3>
                                <div class="grid items-stretch gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach($group as $fee)
                                        @php
                                            $hasEarly = (bool) $fee->price_early_bird;
                                            $mainPrice = $hasEarly ? $fee->price_early_bird : $fee->price_regular;
                                            $benefit = $fee->notes ? trim(strip_tags((string) $fee->notes)) : null;
                                        @endphp
                                        <div class="flex flex-col rounded-2xl bg-white p-6 shadow-[inset_0_0_0_1px_rgba(15,23,42,0.08),0_12px_28px_-16px_rgba(15,23,42,0.18)] transition hover:-translate-y-1">
                                            <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $fee->category }}</h4>
                                            <div class="mt-3 flex items-baseline gap-1.5">
                                                <span class="text-sm font-semibold text-slate-500">{{ $fee->currency }}</span>
                                                <span class="font-display text-3xl font-bold tracking-tight text-[var(--brand-2)]">{{ number_format((float) $mainPrice, 0, ',', '.') }}</span>
                                            </div>
                                            @if($hasEarly)
                                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                                    <span class="chip"><x-ui-icon name="sparkles" class="h-3.5 w-3.5" /> {{ __('site.early_bird') }}</span>
                                                    <span class="text-slate-400 line-through">{{ $fee->currency }} {{ number_format((float) $fee->price_regular, 0, ',', '.') }}</span>
                                                </div>
                                            @endif
                                            @if($benefit)
                                                <p class="mt-3 flex items-start gap-2 text-sm leading-relaxed text-slate-500">
                                                    <x-ui-icon name="check-circle" class="mt-0.5 h-4 w-4 shrink-0 text-[var(--brand)]" />
                                                    {{ $benefit }}
                                                </p>
                                            @endif
                                            <a href="{{ route('registration') }}" class="btn btn-outline mt-5 w-full">{{ __('site.register_now') }}</a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
                <div class="mt-8 text-center">
                    <a href="{{ route('registration') }}" class="inline-flex items-center gap-1.5 font-medium text-[var(--brand)] hover:underline">{{ __('site.view_fees') }} <x-ui-icon name="arrow-right" class="h-4 w-4" /></a>
                </div>
            </div>
        </section>
    @endif

    {{-- IMPORTANT DATES --}}
    @if($importantDates->isNotEmpty())
        <section class="py-16">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :title="__('site.important_dates')" />

                @if($nextDeadline)
                    <div class="mb-6 flex flex-col sm:flex-row items-center justify-between gap-3 rounded-xl bg-gradient-to-r from-[var(--accent)] to-[var(--accent-strong)] text-white px-6 py-4 shadow-lg">
                        <div>
                            <div class="text-xs uppercase tracking-widest text-white/80">{{ __('site.next_deadline') }}</div>
                            <div class="font-semibold">{{ $deadlineLabel($nextDeadline->label) }}</div>
                        </div>
                        <div class="text-lg font-bold">{{ $nextDeadline->date->translatedFormat('d M Y') }}</div>
                    </div>
                @endif

                <ul class="divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white overflow-hidden">
                    @foreach($importantDates as $d)
                        <li class="flex items-center justify-between gap-4 px-5 py-4 {{ $d->is_highlighted ? 'bg-[var(--brand)]/5' : '' }}">
                            <span class="font-medium text-slate-700">{{ $deadlineLabel($d->label) }}</span>
                            <span class="text-sm font-semibold text-[var(--brand-2)] whitespace-nowrap">
                                {{ $d->date?->translatedFormat('d M Y') ?? 'TBA' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- PUBLICATION & INDEXING --}}
    @if($publicationPage)
        <section class="bg-white py-16">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :title="$publicationPage->title ?: __('site.publication_indexing')" />
                <div class="prose prose-slate max-w-none">
                    {!! $publicationPage->content !!}
                </div>
            </div>
        </section>
    @endif

    {{-- GALLERY HIGHLIGHT --}}
    @if($galleries->isNotEmpty())
        <section class="py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :title="__('site.gallery')" />
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    @foreach($galleries as $g)
                        @php $img = $g->getFirstMediaUrl('image', 'thumb'); @endphp
                        <div class="aspect-square rounded-lg overflow-hidden bg-slate-100">
                            @if($img)
                                <img src="{{ $img }}" alt="{{ $g->caption ?? '' }}" loading="lazy" class="h-full w-full object-cover hover:scale-105 transition-transform duration-300">
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- LATEST NEWS --}}
    @if($latestNews->isNotEmpty())
        <section class="bg-white py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :title="__('site.latest_news')" />
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($latestNews as $item)
                        <x-card-news :item="$item" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- FAQ TEASER --}}
    @if($faqs->isNotEmpty())
        <section class="section-tint py-16">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :title="__('site.faq_title')" />
                <div class="space-y-3">
                    @foreach($faqs as $faq)
                        <div x-data="{ open: false }" class="rounded-lg border border-slate-200 overflow-hidden">
                            <button @click="open = !open" :aria-expanded="open" class="w-full flex items-center justify-between gap-4 px-5 py-4 text-left">
                                <span class="font-medium text-slate-800">{{ $faq->question }}</span>
                                <svg class="h-5 w-5 text-slate-400 transition-transform shrink-0" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="open" x-transition x-cloak class="px-5 pb-4 text-slate-600 whitespace-pre-line">{{ $faq->answer }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="text-center mt-6">
                    <a href="{{ route('faq') }}" class="text-[var(--brand)] font-medium hover:underline">{{ __('site.view_all_faq') }} →</a>
                </div>
            </div>
        </section>
    @endif

    {{-- SPONSORS (grouped by tier) --}}
    @if($sponsors->isNotEmpty())
        <section class="bg-white py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :title="__('site.our_sponsors')" />
                <div class="space-y-8">
                    @foreach($tierOrder as $tierKey => $tierLabel)
                        @if($sponsors->has($tierKey))
                            <div>
                                <p class="text-center text-xs uppercase tracking-widest text-slate-400 mb-4">{{ $tierLabel }}</p>
                                <div class="flex flex-wrap items-center justify-center gap-8">
                                    @foreach($sponsors->get($tierKey) as $sponsor)
                                        @php $logo = $sponsor->getFirstMediaUrl('logo', 'thumb'); @endphp
                                        <div class="grayscale hover:grayscale-0 transition">
                                            @if($logo)
                                                <img src="{{ $logo }}" alt="{{ $sponsor->name }}" loading="lazy" class="h-14 w-auto object-contain">
                                            @else
                                                <span class="text-slate-400 font-medium">{{ $sponsor->name }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- CTA BAND --}}
    <section class="bg-[var(--brand-2)]">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-14 text-center text-white">
            <h2 class="text-2xl sm:text-3xl font-bold">{{ __('site.cta_title') }}</h2>
            <p class="mt-2 text-slate-300">{{ __('site.cta_subtitle') }}</p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <a href="{{ route('author.register', ['role' => 'presenter']) }}" class="btn btn-accent">{{ $isId ? 'Mulai Abstract' : 'Start Abstract' }}</a>
                <a href="{{ route('author.register', ['role' => 'non_presenter']) }}" class="btn btn-ghost">{{ $isId ? 'Daftar sebagai Peserta' : 'Register as Attendee' }}</a>
            </div>
        </div>
    </section>
</x-layout>
