@props(['name' => 'ICOMAN 2026'])

@php
    $s = siteSettings();
    $socials = array_filter([
        'Instagram' => $s->social_instagram,
        'Twitter/X' => $s->social_twitter,
        'YouTube' => $s->social_youtube,
    ]);
    $quickLinks = [
        'about' => __('nav.about'),
        'speakers' => __('nav.speakers'),
        'call-for-papers' => __('nav.cfp'),
        'important-dates' => __('nav.dates'),
        'registration' => __('nav.registration'),
        'contact' => __('nav.contact'),
    ];
@endphp

<footer class="bg-[var(--brand-2)] text-slate-300">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-4 lg:px-8">
        <div>
            <h3 class="font-display text-xl font-bold text-white">{{ $name }}</h3>
            <p class="mt-3 text-sm leading-relaxed text-slate-400">International Conference on Management</p>
            @if($s->event_location)
                <p class="mt-3 flex items-start gap-2 text-sm text-slate-400">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    {{ $s->event_location }}
                </p>
            @endif
        </div>

        <div class="text-sm">
            <h4 class="mb-3 font-semibold text-white">{{ app()->getLocale() === 'id' ? 'Tautan' : 'Quick Links' }}</h4>
            <ul class="space-y-2">
                @foreach($quickLinks as $route => $label)
                    <li><a href="{{ route($route) }}" class="text-slate-400 transition-colors hover:text-white">{{ $label }}</a></li>
                @endforeach
            </ul>
        </div>

        <div class="space-y-1 text-sm">
            <h4 class="mb-3 font-semibold text-white">{{ __('site.contact_us') }}</h4>
            @if($s->contact_email)<p class="flex items-start gap-2"><svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg><a href="mailto:{{ $s->contact_email }}" class="break-all hover:text-white">{{ $s->contact_email }}</a></p>@endif
            @if($s->contact_whatsapp)<p>{{ $s->contact_whatsapp }}</p>@endif
            @if($s->contact_address)<p class="text-slate-400">{{ $s->contact_address }}</p>@endif
        </div>

        <div class="text-sm">
            <h4 class="mb-3 font-semibold text-white">{{ app()->getLocale() === 'id' ? 'Ikuti Kami' : 'Follow Us' }}</h4>
            @if(count($socials))
                <div class="flex flex-wrap gap-3">
                    @foreach($socials as $label => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="rounded-lg bg-white/10 px-3 py-1.5 transition-colors hover:bg-white/20">{{ $label }}</a>
                    @endforeach
                </div>
            @else
                <p class="text-slate-500">—</p>
            @endif
            <a href="{{ route('author.register', ['role' => 'presenter']) }}" class="btn btn-primary mt-5 inline-flex text-sm">{{ app()->getLocale() === 'id' ? 'Kirim Abstrak' : 'Submit Abstract' }}</a>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-7xl flex-col justify-between gap-2 px-4 py-4 text-xs text-slate-400 sm:flex-row sm:px-6 lg:px-8">
            <span>&copy; {{ date('Y') }} {{ $name }}. All rights reserved.</span>
            <span></span>
        </div>
    </div>
    <div class="mx-auto max-w-7xl px-4 pb-6 text-sm"><a href="{{ route('privacy', ['lang' => app()->getLocale()]) }}" class="underline">{{ app()->getLocale() === 'id' ? 'Privasi dan penggunaan data' : 'Privacy and data use' }}</a></div>
</footer>
