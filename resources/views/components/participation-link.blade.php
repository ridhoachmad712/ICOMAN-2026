@props(['role' => 'presenter', 'label' => null])
@php
    $presenter = $role === 'presenter';
    $open = currentEdition() && app(\App\Services\ConferenceDeadlines::class)->isOpen($presenter ? 'abstract' : 'payment');
    $url = $open ? route('author.register.terms', ['role' => $role]) : route('filament.author.pages.author-dashboard');
@endphp
<a href="{{ $url }}" {{ $attributes->class(['btn']) }}>
    {{ $open ? ($label ?: __('site.home_submit_abstract')) : __('site.public_portal') }}
</a>
