<x-author-layout :title="$submission->submission_number">
    @php
        $badge = match($submission->status) {
            'submitted' => 'bg-slate-100 text-slate-700',
            'under_review' => 'bg-blue-100 text-blue-700',
            'revision_required' => 'bg-amber-100 text-amber-700',
            'accepted' => 'bg-emerald-100 text-emerald-700',
            'rejected' => 'bg-red-100 text-red-700',
            default => 'bg-slate-100 text-slate-700',
        };
        $paper = $submission->getFirstMediaUrl('paper');
        $cameraReady = $submission->getFirstMediaUrl('camera_ready');
    @endphp

    <a href="{{ route('author.dashboard') }}" class="text-sm text-[var(--brand)] hover:underline">← {{ __('author.back_dashboard') }}</a>

    <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="font-mono text-xs text-slate-500">{{ $submission->submission_number }}</p>
            <h1 class="text-2xl font-bold text-[var(--brand-2)]">{{ $submission->title }}</h1>
        </div>
        <span class="inline-block rounded-full px-3 py-1 text-sm font-semibold {{ $badge }}">
            {{ ucwords(str_replace('_', ' ', $submission->status)) }}
        </span>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <div class="card p-6">
                <h2 class="font-semibold text-slate-800 mb-2">{{ __('author.abstract') }}</h2>
                <p class="text-sm text-slate-600 whitespace-pre-line">{{ $submission->abstract }}</p>
                @if($submission->abstract_id)
                    <h3 class="font-semibold text-slate-700 mt-4 mb-1 text-sm">{{ __('author.abstract_id') }}</h3>
                    <p class="text-sm text-slate-600 whitespace-pre-line">{{ $submission->abstract_id }}</p>
                @endif
            </div>

            @if($reviews->isNotEmpty())
                <div class="card p-6">
                    <h2 class="font-semibold text-slate-800 mb-4">{{ __('author.review_result') }}</h2>
                    <div class="space-y-4">
                        @foreach($reviews as $r)
                            <div class="rounded-lg border border-slate-200 p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('author.reviewer_num', ['num' => $loop->iteration]) }}</span>
                                    <span class="text-xs rounded-full bg-slate-100 px-2 py-0.5 text-slate-600">
                                        {{ ucwords(str_replace('_', ' ', $r->recommendation ?? '')) }}
                                    </span>
                                </div>
                                @if($r->comments_for_author)
                                    <p class="text-sm text-slate-600 whitespace-pre-line">{{ $r->comments_for_author }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($submission->status === 'accepted')
                <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-6">
                    <h2 class="font-semibold text-emerald-800 mb-2">{{ __('author.camera_ready') }}</h2>
                    <p class="text-sm text-emerald-700 mb-4">{{ __('author.camera_ready_accepted') }}</p>
                    @if($cameraReady)
                        <p class="text-sm mb-3">✓ <a href="{{ $cameraReady }}" target="_blank" class="text-emerald-700 underline">{{ __('author.uploaded_view') }}</a></p>
                    @endif
                    <form method="POST" action="{{ route('author.submissions.camera-ready', $submission) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                        @csrf
                        <input type="file" name="camera_ready" accept=".pdf,.doc,.docx" required
                               class="text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-4 file:py-2 file:text-white file:font-semibold">
                        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:opacity-90">{{ __('author.upload') }}</button>
                    </form>
                    @error('camera_ready')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="card p-6">
                <h2 class="font-semibold text-slate-800 mb-3">{{ __('author.files') }}</h2>
                @if($paper)
                    <a href="{{ $paper }}" target="_blank" class="inline-flex items-center gap-2 text-sm font-medium text-[var(--brand)] hover:underline">↓ {{ __('author.download_paper') }}</a>
                @else
                    <p class="text-sm text-slate-400">—</p>
                @endif
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('author.topic') }}</dt><dd class="text-slate-700">{{ $submission->topic?->title ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('author.submitted') }}</dt><dd class="text-slate-700">{{ $submission->submitted_at?->format('d M Y') }}</dd></div>
                </dl>
            </div>

            <div class="card p-6">
                <h2 class="font-semibold text-slate-800 mb-3">{{ __('author.authors') }}</h2>
                <ul class="space-y-2 text-sm">
                    @foreach($submission->authors as $a)
                        <li>
                            <span class="text-slate-800">{{ $a->name }}</span>
                            @if($a->is_corresponding)<span class="text-[10px] text-[var(--brand)] font-semibold">✻</span>@endif
                            @if($a->affiliation)<span class="block text-xs text-slate-400">{{ $a->affiliation }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</x-author-layout>
