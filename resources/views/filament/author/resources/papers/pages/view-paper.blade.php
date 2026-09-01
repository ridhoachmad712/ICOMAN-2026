<x-filament-panels::page>
    @php
        $id = app()->getLocale() === 'id';
        $record->loadMissing(['topic', 'authors', 'registrations', 'author', 'reviewAssignments.review']);
        $reviews = \App\Models\Review::query()
            ->whereNotNull('submitted_at')
            ->whereHas('assignment', fn ($query) => $query->where('submission_id', $record->id))
            ->with('assignment')
            ->latest('submitted_at')
            ->get();
        $extendedReviews = $reviews->where('assignment.phase', 'extended_abstract');
        $statusLabel = fn (string $status) => $id ? (\App\Models\Submission::STATUS_LABELS[$status] ?? ucwords(str_replace('_', ' ', $status))) : ucwords(str_replace('_', ' ', $status));
        $statusColor = match($record->status) {
            'accepted' => 'success',
            'rejected' => 'danger',
            'extended_abstract_submitted' => 'warning',
            default => 'info',
        };
        $steps = app(\App\Services\AuthorJourney::class)->timeline($record->author, collect([$record]), $record->registrations);
    @endphp

    <div class="space-y-6">
        @if(session('status'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-800">{{ session('status') }}</div>
        @endif

        <x-filament::section>
            <x-slot name="heading">{{ $record->title }}</x-slot>
            <x-slot name="description">{{ $record->submission_number }}@if($record->topic) · {{ $record->topic->title }}@endif</x-slot>
            <x-slot name="afterHeader"><x-filament::badge :color="$statusColor">{{ $statusLabel($record->status) }}</x-filament::badge></x-slot>

            <div class="author-policy-note">
                <x-filament::icon icon="heroicon-o-information-circle" class="h-5 w-5 shrink-0" />
                <span>{{ $id ? 'Paper ini menggunakan satu-satunya kuota submission akun Anda untuk edisi ini.' : 'This paper uses your account’s single submission quota for this edition.' }}</span>
            </div>

            @include('filament.author.components.journey-timeline', ['steps' => $steps])
        </x-filament::section>

        @if(in_array($record->status, ['extended_abstract_draft', 'abstract_submitted', 'abstract_approved'], true))
            <x-filament::section icon="heroicon-o-document-text" icon-color="primary">
                <x-slot name="heading">{{ $id ? 'Edit submission' : 'Edit submission' }}</x-slot>
                <x-slot name="description">{{ $id ? 'Judul, topik, keywords, daftar penulis, dan seluruh isi naskah masih dapat diubah sebelum dikirim ke reviewer.' : 'The title, topic, keywords, author list, and all manuscript sections remain editable before reviewer submission.' }}</x-slot>
                <div class="flex flex-wrap gap-3">
                    <x-filament::button tag="a" href="{{ \App\Filament\Author\Resources\Papers\PaperResource::getUrl('extended-abstract', ['record' => $record]) }}" icon="heroicon-o-pencil-square">
                        {{ $id ? 'Edit submission' : 'Edit submission' }}
                    </x-filament::button>
                    @if($record->extended_abstract_draft_saved_at)
                        <x-filament::button tag="a" color="gray" outlined href="{{ route('author.submissions.extended-abstract.preview', $record) }}" target="_blank" icon="heroicon-o-document-magnifying-glass">
                            {{ $id ? 'Preview PDF' : 'PDF Preview' }}
                        </x-filament::button>
                    @endif
                </div>
            </x-filament::section>
        @endif

        @if(in_array($record->status, ['extended_abstract_submitted', 'extended_abstract_under_review'], true))
            <x-filament::section icon="heroicon-o-clock" icon-color="info">
                <x-slot name="heading">{{ $id ? 'Menunggu verifikasi reviewer' : 'Awaiting reviewer verification' }}</x-slot>
                <x-slot name="description">{{ $id ? 'Extended abstract sudah terkirim. Tidak ada tindakan tambahan sampai reviewer menyelesaikan verifikasi.' : 'Your extended abstract was submitted. No further action is required until reviewer verification is complete.' }}</x-slot>
                <x-filament::button tag="a" color="gray" outlined href="{{ route('author.submissions.extended-abstract.preview', $record) }}" target="_blank" icon="heroicon-o-document-magnifying-glass">{{ $id ? 'Buka PDF' : 'Open PDF' }}</x-filament::button>
            </x-filament::section>
        @endif

        @if($record->status === 'accepted')
            <x-filament::section icon="heroicon-o-check-circle" icon-color="success">
                <x-slot name="heading">Accepted</x-slot>
                <x-slot name="description">{{ $id ? 'Extended abstract telah diverifikasi dan dinyatakan diterima.' : 'Your extended abstract was verified and accepted.' }}</x-slot>
                <div class="flex flex-wrap gap-3">
                    <x-filament::button tag="a" color="gray" outlined href="{{ route('author.submissions.extended-abstract.preview', $record) }}" target="_blank">{{ $id ? 'Buka PDF' : 'Open PDF' }}</x-filament::button>
                    <x-filament::button tag="a" color="gray" href="{{ route('author.submissions.loa', $record) }}" target="_blank">{{ $id ? 'Buka Letter of Acceptance' : 'Open Letter of Acceptance' }}</x-filament::button>
                </div>
            </x-filament::section>
        @endif

        @if($record->status === 'rejected')
            <x-filament::section icon="heroicon-o-x-circle" icon-color="danger">
                <x-slot name="heading">{{ $id ? 'Belum lolos' : 'Not approved' }}</x-slot>
                <x-slot name="description">{{ $id ? 'Lihat catatan reviewer di bawah untuk memahami hasil penilaian.' : 'Read the reviewer feedback below to understand the decision.' }}</x-slot>
            </x-filament::section>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-filament::section>
                    <x-slot name="heading">{{ $id ? 'Informasi paper' : 'Paper information' }}</x-slot>
                    <div class="space-y-5 text-sm leading-7 text-gray-700">
                        <div><p class="mb-2 font-semibold text-gray-950">Keywords</p><div class="flex flex-wrap gap-2">@foreach($record->keywords ?? [] as $keyword)<x-filament::badge color="gray">{{ $keyword }}</x-filament::badge>@endforeach</div></div>
                    </div>
                </x-filament::section>

                @if($record->extended_abstract || $record->extended_abstract_draft_saved_at)
                    <x-filament::section>
                        <x-slot name="heading">Extended Abstract</x-slot>
                        <x-slot name="description">{{ $record->extended_abstract_submitted_at?->format('d M Y, H:i') }}</x-slot>
                        @include('components.extended-abstract-document', ['submission' => $record])
                    </x-filament::section>
                @endif

                @foreach([[$extendedReviews, $id ? 'Hasil verifikasi reviewer' : 'Reviewer verification result']] as [$phaseReviews, $heading])
                    @if($phaseReviews->isNotEmpty())
                        <x-filament::section>
                            <x-slot name="heading">{{ $heading }}</x-slot>
                            <div class="space-y-4">
                                @foreach($phaseReviews as $index => $review)
                                    <div class="rounded-xl border border-gray-200 p-4">
                                        <div class="mb-3 flex items-center justify-between gap-3"><p class="text-sm font-semibold text-gray-950">Reviewer {{ $index + 1 }}</p><x-filament::badge color="gray">{{ ucwords(str_replace('_', ' ', $review->recommendation)) }}</x-filament::badge></div>
                                        <p class="whitespace-pre-line text-sm leading-6 text-gray-700">{{ $review->comments_for_author ?: ($id ? 'Tidak ada komentar tertulis.' : 'No written comments.') }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </x-filament::section>
                    @endif
                @endforeach
            </div>

            <div class="space-y-6">
                <x-filament::section>
                    <x-slot name="heading">{{ $id ? 'Ringkasan' : 'Summary' }}</x-slot>
                    <dl class="divide-y divide-gray-200 text-sm">
                        <div class="flex justify-between gap-4 py-3 first:pt-0"><dt class="text-gray-500">{{ $id ? 'Kuota submission' : 'Submission quota' }}</dt><dd class="font-medium text-gray-950">1/1</dd></div>
                        <div class="flex justify-between gap-4 py-3"><dt class="text-gray-500">{{ $id ? 'Dibuat' : 'Created' }}</dt><dd class="font-medium text-gray-950">{{ $record->submitted_at?->format('d M Y') }}</dd></div>
                        <div class="flex justify-between gap-4 py-3 last:pb-0"><dt class="text-gray-500">{{ $id ? 'Penulis' : 'Authors' }}</dt><dd class="font-medium text-gray-950">{{ $record->authors->count() }}</dd></div>
                    </dl>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">{{ $id ? 'Penulis' : 'Authors' }}</x-slot>
                    <div class="space-y-4">
                        @foreach($record->authors->sortBy('order') as $author)
                            <div><div class="flex flex-wrap items-center gap-2"><p class="text-sm font-medium text-gray-950">{{ $author->name }}</p>@if($author->is_corresponding)<x-filament::badge color="primary">Corresponding</x-filament::badge>@endif</div><p class="mt-1 text-xs text-gray-500">{{ $author->email }}@if($author->affiliation) · {{ $author->affiliation }}@endif</p></div>
                        @endforeach
                    </div>
                </x-filament::section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
