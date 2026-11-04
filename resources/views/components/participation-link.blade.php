@props(['role' => 'presenter', 'label' => null])
@php
    $open = currentEdition() && \App\Http\Controllers\Author\AuthController::isRoleOpen($role);
    $url = $open ? route('author.register.terms', ['role' => $role]) : route('filament.author.pages.author-dashboard');
@endphp
<a href="{{ $url }}" {{ $attributes->class(['btn']) }}>
    {{ $open ? ($label ?: __('site.home_submit_abstract')) : __('site.public_portal') }}
</a>
