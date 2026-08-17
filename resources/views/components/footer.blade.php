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

<footer class="bg-[var(--brand-2)] text-slate-300 mt-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-14 grid gap-10 md:grid-cols-4">
        <div class="md:col-span-1">
            <h3 class="font-display text-white text-xl font-bold">{{ $name }}</h3>
            <p class="mt-3 text-sm text-slate-400 leading-relaxed">International Conference on Management</p>
            @if($s->event_location)<p class="mt-3 text-sm text-slate-400">📍 {{ $s->event_location }}</p>@endif
        </div>

        <div class="text-sm">
            <h4 class="text-white font-semibold mb-3">{{ app()->getLocale() === 'id' ? 'Tautan' : 'Quick Links' }}</h4>
            <ul class="space-y-2">
                @foreach($quickLinks as $route => $label)
                    <li><a href="{{ route($route) }}" class="text-slate-400 hover:text-white transition-colors">{{ $label }}</a></li>
                @endforeach
            </ul>
        </div>

        <div class="text-sm space-y-1">
            <h4 class="text-white font-semibold mb-3">{{ __('site.contact_us') }}</h4>
            @if($s->contact_email)<p>✉ <a href="mailto:{{ $s->contact_email }}" class="hover:text-white">{{ $s->contact_email }}</a></p>@endif
            @if($s->contact_whatsapp)<p>☎ {{ $s->contact_whatsapp }}</p>@endif
            @if($s->contact_address)<p class="text-slate-400">{{ $s->contact_address }}</p>@endif
        </div>

        <div class="text-sm">
            <h4 class="text-white font-semibold mb-3">{{ app()->getLocale() === 'id' ? 'Ikuti Kami' : 'Follow Us' }}</h4>
            @if(count($socials))
                <div class="flex flex-wrap gap-3">
                    @foreach($socials as $label => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="rounded-lg bg-white/10 px-3 py-1.5 hover:bg-white/20 transition-colors">{{ $label }}</a>
                    @endforeach
                </div>
            @else
                <p class="text-slate-500">—</p>
            @endif
            <a href="{{ route('author.register') }}" class="mt-5 inline-flex btn btn-primary text-sm">{{ __('site.submit_paper') }}</a>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4 text-xs text-slate-400 flex flex-col sm:flex-row justify-between gap-2">
            <span>&copy; {{ date('Y') }} {{ $name }}. All rights reserved.</span>
            <span>Powered by Laravel &amp; Filament</span>
        </div>
    </div>
</footer>
