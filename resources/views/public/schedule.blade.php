<x-layout :title="__('nav.program')">
    <x-page-header :title="__('site.program_schedule')" />

    <p class="mx-auto mt-6 max-w-6xl px-4 text-sm text-slate-600">{{ app()->getLocale() === 'id' ? 'Semua waktu dalam WITA (UTC+8).' : 'All times are in Central Indonesia Time (UTC+8).' }}</p>
    <section class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-14 space-y-12">
        @forelse($schedules as $day => $sessions)
            <div>
                <h2 class="text-xl font-bold text-[var(--brand-2)] mb-4">
                    {{ $day ? \Illuminate\Support\Carbon::parse($day)->translatedFormat('l, d F Y') : 'TBA' }}
                </h2>
                <div class="overflow-x-auto card">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 w-32">{{ __('site.time') }}</th>
                                <th class="px-4 py-3">{{ __('site.session') }}</th>
                                <th class="px-4 py-3">{{ __('site.speaker') }}</th>
                                <th class="px-4 py-3">{{ __('site.room') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($sessions as $s)
                                <tr class="{{ $s->session_type === 'break' ? 'bg-slate-50/60 text-slate-500' : '' }}">
                                    <td class="px-4 py-3 whitespace-nowrap font-medium text-slate-600">
                                        {{ $s->time_start ? \Illuminate\Support\Carbon::parse($s->time_start)->format('H:i') : '' }}
                                        @if($s->time_end)–{{ \Illuminate\Support\Carbon::parse($s->time_end)->format('H:i') }}@endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-800">{{ $s->title }}</td>
                                    <td class="px-4 py-3 text-slate-500">{{ $s->speaker_name }}</td>
                                    <td class="px-4 py-3 text-slate-500">{{ $s->room }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <x-empty-state />
        @endforelse
    </section>
</x-layout>
