@php
    $id = app()->getLocale() === 'id';
    $deadlines = app(\App\Services\ConferenceDeadlines::class);
@endphp
<div class="grid gap-4 sm:grid-cols-3">
    @foreach(['abstract' => ['Abstrak', 'Abstract'], 'payment' => ['Pembayaran', 'Payment'], 'full_paper' => ['Full paper', 'Full paper']] as $kind => $labels)
        @php $date = $deadlines->date($kind); @endphp
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <p class="text-sm font-semibold text-slate-900">{{ $labels[$id ? 0 : 1] }}</p>
            <p class="mt-2 text-sm text-slate-600">{{ $date ? $date->format('d M Y, H:i').' WITA (UTC+8)' : ($id ? 'Akan diumumkan' : 'To be announced') }}</p>
            @if($date && $date->isPast())<p class="mt-2 text-xs font-semibold text-red-700">{{ $id ? 'Pengiriman ditutup' : 'Closed' }}</p>@endif
        </div>
    @endforeach
</div>
