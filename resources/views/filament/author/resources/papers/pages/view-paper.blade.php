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
            'revision_required', 'extended_abstract_submitted' => 'warning',
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

        @if($record->status === 'revision_required')
            <x-filament::section icon="heroicon-o-arrow-uturn-left" icon-color="warning">
                <x-slot name="heading">{{ $id ? 'Revisi diminta reviewer' : 'Revision requested' }}</x-slot>
                <x-slot name="description">{{ $id ? 'Perbaiki naskah sesuai catatan reviewer di bawah, lalu kirim ulang untuk putaran review berikutnya.' : 'Revise the manuscript according to the reviewer comments below, then resubmit for the next review round.' }}</x-slot>
                <div class="flex flex-wrap gap-3">
                    <x-filament::button tag="a" color="warning" href="{{ \App\Filament\Author\Resources\Papers\PaperResource::getUrl('extended-abstract', ['record' => $record]) }}" icon="heroicon-o-pencil-square">
                        {{ $id ? 'Perbaiki & Kirim Ulang' : 'Revise & Resubmit' }}
                    </x-filament::button>
                    <x-filament::button tag="a" color="gray" outlined href="{{ route('author.submissions.extended-abstract.preview', $record) }}" target="_blank" icon="heroicon-o-document-magnifying-glass">{{ $id ? 'Preview PDF' : 'PDF Preview' }}</x-filament::button>
                </div>
            </x-filament::section>
        @endif

        @if(in_array($record->status, ['extended_abstract_submitted', 'extended_abstract_under_review'], true))
            <x-filament::section icon="heroicon-o-clock" icon-color="info">
                <x-slot name="heading">{{ $id ? 'Menunggu verifikasi reviewer' : 'Awaiting reviewer verification' }}</x-slot>
                <x-slot name="description">{{ $id ? 'Abstract sudah terkirim. Tidak ada tindakan tambahan sampai reviewer menyelesaikan verifikasi.' : 'Your abstract was submitted. No further action is required until reviewer verification is complete.' }}</x-slot>
                <x-filament::button tag="a" color="gray" outlined href="{{ route('author.submissions.extended-abstract.preview', $record) }}" target="_blank" icon="heroicon-o-document-magnifying-glass">{{ $id ? 'Buka PDF' : 'Open PDF' }}</x-filament::button>
            </x-filament::section>
        @endif

        @if($record->status === 'accepted' && ! $record->isLoaIssued())
            <x-filament::section icon="heroicon-o-check-circle" icon-color="success">
                <x-slot name="heading">Accepted</x-slot>
                <x-slot name="description">{{ $id ? 'Abstract Anda diterima. Panitia sedang menyiapkan Letter of Acceptance (LOA) — akan otomatis muncul di sini.' : 'Your abstract is accepted. The committee is preparing your Letter of Acceptance (LOA) — it will appear here automatically.' }}</x-slot>
                <x-filament::button tag="a" color="gray" outlined href="{{ route('author.submissions.extended-abstract.preview', $record) }}" target="_blank">{{ $id ? 'Buka PDF' : 'Open PDF' }}</x-filament::button>
            </x-filament::section>
        @endif

        @if($record->status === 'accepted' && $record->isLoaIssued())
            <x-filament::section icon="heroicon-o-check-circle" icon-color="success">
                <x-slot name="heading">Accepted · LOA {{ $id ? 'terbit' : 'issued' }}</x-slot>
                <x-slot name="description">{{ $id ? 'Letter of Acceptance sudah tersedia. Selesaikan pembayaran registrasi presenter untuk mengunci slot Anda.' : 'Your Letter of Acceptance is available. Complete the presenter registration payment to secure your slot.' }}</x-slot>
                <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-gray-500">{{ $id ? 'Target jurnal:' : 'Journal target:' }}</span>
                    <x-filament::badge :color="$record->journal_target === 'sinta3' ? 'warning' : 'gray'">{{ $record->journalTargetLabel() }}</x-filament::badge>
                    @if($record->sinta3_offered && $record->journal_target !== 'sinta3')
                        <span class="text-xs text-warning-700">{{ $id ? '· Anda ditawari opsi penerbitan SINTA 3 (biaya tambahan) saat pembayaran.' : '· You are offered a SINTA 3 publication option (extra fee) at payment.' }}</span>
                    @endif
                </div>
                <div class="flex flex-wrap gap-3">
                    <x-filament::button tag="a" color="gray" href="{{ route('author.submissions.loa', $record) }}" target="_blank" icon="heroicon-o-document-check">{{ $id ? 'Buka Letter of Acceptance' : 'Open Letter of Acceptance' }}</x-filament::button>
                    <x-filament::button tag="a" color="gray" outlined href="{{ route('author.submissions.extended-abstract.preview', $record) }}" target="_blank">{{ $id ? 'Buka PDF' : 'Open PDF' }}</x-filament::button>
                </div>
            </x-filament::section>
        @endif

        @if($record->status === 'accepted' && $record->isLoaIssued())
            @php $paidForPaper = $record->registrations->firstWhere('status', 'paid'); @endphp
            <x-filament::section icon="heroicon-o-document-arrow-up" icon-color="primary">
                <x-slot name="heading">{{ $id ? 'Naskah lengkap (Full Paper)' : 'Full paper' }}</x-slot>
                <x-slot name="description">
                    {{ $id ? 'Setelah pembayaran terverifikasi, unggah naskah lengkap sesuai template & tenggat panitia (PDF/DOC/DOCX, maks 20 MB).' : 'After your payment is verified, upload the full manuscript per the committee template & deadline (PDF/DOC/DOCX, max 20 MB).' }}
                </x-slot>

                @if(! $paidForPaper)
                    <div class="author-policy-note">
                        <x-filament::icon icon="heroicon-o-lock-closed" class="h-5 w-5 shrink-0" />
                        <span>{{ $id ? 'Pengiriman full paper akan terbuka setelah pembayaran registrasi Anda terverifikasi.' : 'Full paper submission unlocks once your registration payment is verified.' }}</span>
                    </div>
                @else
                    @if($record->hasFullPaper())
                        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm">
                            <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5 shrink-0 text-success-600" />
                            <span class="text-success-800">
                                {{ $id ? 'Terkirim' : 'Submitted' }}: <strong>{{ $record->fullPaperMedia()?->file_name }}</strong>
                                @if($record->full_paper_submitted_at)· {{ $record->full_paper_submitted_at->format('d M Y, H:i') }}@endif
                            </span>
                            <a class="ml-auto text-primary-600 hover:underline" href="{{ route('author.submissions.full-paper.download', $record) }}">{{ $id ? 'Unduh' : 'Download' }}</a>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('author.submissions.full-paper', $record) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                        @csrf
                        <input type="file" name="full_paper" required accept=".pdf,.doc,.docx"
                               class="block text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-500" />
                        <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray">
                            {{ $record->hasFullPaper() ? ($id ? 'Ganti file' : 'Replace file') : ($id ? 'Kirim full paper' : 'Submit full paper') }}
                        </x-filament::button>
                    </form>
                    @error('full_paper')<p class="mt-2 text-sm text-danger-600">{{ $message }}</p>@enderror
                @endif
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
                        <x-slot name="heading">Abstract</x-slot>
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
