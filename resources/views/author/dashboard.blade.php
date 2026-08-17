<x-author-layout :title="__('author.dashboard')">
    @php
        $badge = fn ($status) => match($status) {
            'submitted' => 'bg-slate-100 text-slate-700',
            'under_review' => 'bg-blue-100 text-blue-700',
            'revision_required' => 'bg-amber-100 text-amber-700',
            'accepted' => 'bg-emerald-100 text-emerald-700',
            'rejected' => 'bg-red-100 text-red-700',
            'paid' => 'bg-emerald-100 text-emerald-700',
            'pending_verification' => 'bg-amber-100 text-amber-700',
            'failed' => 'bg-red-100 text-red-700',
            default => 'bg-slate-100 text-slate-700',
        };
    @endphp

    <div class="flex items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-[var(--brand-2)]">{{ __('author.hello') }}, {{ $author->name }}</h1>
            <p class="text-slate-500 text-sm">{{ __('author.manage_papers') }}</p>
        </div>
        <a href="{{ route('author.submissions.create') }}" class="btn btn-primary whitespace-nowrap">+ {{ __('author.submit_paper') }}</a>
    </div>

    {{-- Papers --}}
    <div class="card overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-semibold text-slate-800">{{ __('author.my_papers') }}</h2>
        </div>
        @if($submissions->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-3">{{ __('author.col_number') }}</th>
                            <th class="px-6 py-3">{{ __('author.col_title') }}</th>
                            <th class="px-6 py-3">{{ __('author.col_topic') }}</th>
                            <th class="px-6 py-3">{{ __('author.col_status') }}</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($submissions as $sub)
                            <tr>
                                <td class="px-6 py-4 font-mono text-xs text-slate-500">{{ $sub->submission_number }}</td>
                                <td class="px-6 py-4 font-medium text-slate-800">{{ $sub->title }}</td>
                                <td class="px-6 py-4 text-slate-500">{{ $sub->topic?->title ?? '—' }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badge($sub->status) }}">
                                        {{ ucwords(str_replace('_', ' ', $sub->status)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('author.submissions.show', $sub) }}" class="text-[var(--brand)] font-medium hover:underline">{{ __('author.detail') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-12 text-center text-slate-400">
                {{ __('author.no_papers') }} <a href="{{ route('author.submissions.create') }}" class="text-[var(--brand)] hover:underline">{{ __('author.submit_first') }} →</a>
            </div>
        @endif
    </div>

    {{-- Registrations --}}
    <div class="mt-8 card overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-semibold text-slate-800">{{ __('author.my_registrations') }}</h2>
            <a href="{{ route('author.registration.create') }}" class="btn btn-primary text-sm">+ {{ __('author.register_participant') }}</a>
        </div>
        @if($registrations->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-3">{{ __('author.col_category') }}</th>
                            <th class="px-6 py-3">{{ __('author.col_amount') }}</th>
                            <th class="px-6 py-3">{{ __('author.col_method') }}</th>
                            <th class="px-6 py-3">{{ __('author.col_status') }}</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($registrations as $reg)
                            <tr>
                                <td class="px-6 py-4 font-medium text-slate-800">{{ $reg->registrationFee?->category ?? '—' }}</td>
                                <td class="px-6 py-4 text-slate-600">IDR {{ number_format((float) $reg->amount, 0, ',', '.') }}</td>
                                <td class="px-6 py-4 text-slate-500">{{ ucfirst($reg->payment_method) }}</td>
                                <td class="px-6 py-4"><span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badge($reg->status) }}">{{ ucwords(str_replace('_', ' ', $reg->status)) }}</span></td>
                                <td class="px-6 py-4 text-right"><a href="{{ route('author.registration.show', $reg) }}" class="text-[var(--brand)] font-medium hover:underline">{{ __('author.detail') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-10 text-center text-slate-400">
                {{ __('author.no_registration') }} <a href="{{ route('author.registration.create') }}" class="text-[var(--brand)] hover:underline">{{ __('author.register_now_link') }} →</a>
            </div>
        @endif
    </div>
</x-author-layout>
