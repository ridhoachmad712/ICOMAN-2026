@php
    $deadline = app(\App\Services\ConferenceDeadlines::class)->date('abstract');
@endphp
<p {{ $attributes->class(['text-sm leading-6']) }}>
    @if(! currentEdition())
        {{ __('site.deadline_to_be_announced') }}
    @elseif($deadline && $deadline->isPast())
        {{ __('site.public_submission_closed') }}
    @elseif($deadline)
        {{ __('site.public_abstract_deadline') }}: {{ $deadline->translatedFormat('d M Y, H:i') }} WITA (UTC+8).
    @else
        {{ __('site.public_abstract_deadline') }}: {{ __('site.deadline_to_be_announced') }}.
    @endif
</p>
