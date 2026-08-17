@props(['date' => null])

@php
    $targetMs = $date ? \Illuminate\Support\Carbon::parse($date)->startOfDay()->getTimestampMs() : null;
@endphp

@if($targetMs)
    <div x-data="{
            target: {{ $targetMs }},
            days: 0, hours: 0, minutes: 0, seconds: 0,
            tick() {
                let diff = Math.max(0, this.target - Date.now());
                let s = Math.floor(diff / 1000);
                this.days = Math.floor(s / 86400);
                this.hours = Math.floor((s % 86400) / 3600);
                this.minutes = Math.floor((s % 3600) / 60);
                this.seconds = s % 60;
            }
         }"
         x-init="tick(); setInterval(() => tick(), 1000)"
         class="grid grid-cols-4 gap-3 max-w-md">
        @foreach(['days' => __('site.days'), 'hours' => __('site.hours'), 'minutes' => __('site.minutes'), 'seconds' => __('site.seconds')] as $key => $label)
            <div class="rounded-lg bg-white/10 backdrop-blur px-2 py-3 text-center">
                <div class="text-3xl font-bold tabular-nums" x-text="String({{ $key }}).padStart(2, '0')">00</div>
                <div class="text-[11px] uppercase tracking-wide text-white/70 mt-1">{{ $label }}</div>
            </div>
        @endforeach
    </div>
@endif
