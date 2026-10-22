<x-filament-panels::page>
    <p class="text-sm"><a href="{{ route('author-guidelines', ['lang' => app()->getLocale()]) }}" class="font-semibold underline">{{ app()->getLocale() === 'id' ? 'Panduan, template, dan deadline' : 'Guidelines, templates, and deadlines' }}</a></p>
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
        $assignments = $record->reviewAssignments->where('phase', 'extended_abstract');
        $waitingAssignment = $assignments->isEmpty();
        $waitingDecision = $assignments->isNotEmpty() && $assignments->every(fn ($assignment) => $assignment->status === 'completed');
        $paidForPaper = $record->registrations->firstWhere('status', 'paid');
        $templateChoiceReady = $record->journalTargetIsChosen() || ! $record->sinta3_offered || (bool) $paidForPaper;
        $templateUrl = $templateChoiceReady ? $record->paperTemplateUrl() : null;
        $fullPaperOpen = app(\App\Services\ConferenceDeadlines::class)->isOpen('full_paper', $record->edition_id);
        $statusLabel = fn (string $status) => $id ? (\App\Models\Submission::STATUS_LABELS[$status] ?? ucwords(str_replace('_', ' ', $status))) : ucwords(str_replace('_', ' ', $status));
        $statusColor = match($record->status) {
            'accepted' => 'success',
            'rejected' => 'danger',
            'revision_required', 'extended_abstract_submitted' => 'warning',
            default => 'info',
        };
    @endphp

    <div class="space-y-6">
        @if(session('status'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-800">{{ session('status') }}</div>
        @endif

        <x-filament::section>
            <x-slot name="heading">{{ $record->title }}</x-slot>
            <x-slot name="description">{{ $record->submission_number }}@if($record->topic) · {{ $record->topic->title }}@endif</x-slot>
            <x-slot name="afterHeader"><x-filament::badge :color="$statusColor">{{ $statusLabel($record->status) }}</x-filament::badge></x-slot>

            <p class="text-sm text-gray-600">{{ $id ? 'Satu akun hanya dapat mengirim satu paper pada edisi ini.' : 'Each account may submit one paper in this edition.' }}</p>
        </x-filament::section>

        @if(in_array($record->status, ['extended_abstract_draft'], true))
            <x-filament::section icon="heroicon-o-document-text" icon-color="primary">
                <x-slot name="heading">{{ $id ? 'Edit submission' : 'Edit submission' }}</x-slot>
                <x-slot name="description">{{ $id ? 'Lengkapi abstract. Simpan draft dan periksa PDF sebelum dikirim ke reviewer.' : 'Complete your abstract. Save a draft and check the PDF before reviewer submission.' }}</x-slot>
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
                @if($extendedReviews->isNotEmpty())
                    <div class="mb-4 rounded-lg border border-warning-200 bg-warning-50 p-4 text-sm text-gray-800">
                        <p class="font-semibold">{{ $id ? 'Catatan reviewer terbaru' : 'Latest reviewer feedback' }}</p>
                        <p class="mt-1 whitespace-pre-line">{{ $extendedReviews->first()?->comments_for_author ?: ($id ? 'Lihat seluruh hasil review di bawah.' : 'See the full review results below.') }}</p>
                    </div>
                @endif
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
                <x-slot name="heading">{{ $waitingAssignment ? ($id ? 'Menunggu penugasan reviewer' : 'Awaiting reviewer assignment') : ($waitingDecision ? ($id ? 'Menunggu keputusan panitia' : 'Awaiting committee decision') : ($id ? 'Sedang direview' : 'Under review')) }}</x-slot>
                <x-slot name="description">{{ $waitingAssignment ? ($id ? 'Naskah sudah terkirim. Panitia akan menugaskan reviewer.' : 'Your manuscript is submitted. The committee will assign reviewers.') : ($waitingDecision ? ($id ? 'Penilaian reviewer selesai. Panitia sedang menetapkan keputusan.' : 'The reviews are complete. The committee is making a decision.') : ($id ? 'Reviewer sedang menilai naskah. Anda belum perlu melakukan apa pun.' : 'Reviewers are assessing your manuscript. No action is needed now.')) }}</x-slot>
                <x-filament::button tag="a" color="gray" outlined href="{{ route('author.submissions.extended-abstract.preview', $record) }}" target="_blank" icon="heroicon-o-document-magnifying-glass">{{ $id ? 'Buka PDF' : 'Open PDF' }}</x-filament::button>
            </x-filament::section>
        @endif

        @if($record->status === 'accepted' && ! $record->isLoaIssued())
            <x-filament::section icon="heroicon-o-check-circle" icon-color="success">
                <x-slot name="heading">Accepted</x-slot>
                <x-slot name="description">{{ $id ? 'Abstract diterima. Panitia sedang menyiapkan Letter of Acceptance (LOA).' : 'Your abstract is accepted. The committee is preparing your Letter of Acceptance (LOA).' }}</x-slot>
                <x-filament::button tag="a" color="gray" outlined href="{{ route('author.submissions.extended-abstract.preview', $record) }}" target="_blank">{{ $id ? 'Buka PDF' : 'Open PDF' }}</x-filament::button>
            </x-filament::section>
        @endif

        @if($record->status === 'accepted' && $record->isLoaIssued())
            <x-filament::section icon="heroicon-o-check-circle" icon-color="success">
                <x-slot name="heading">Accepted · LOA {{ $id ? 'terbit' : 'issued' }}</x-slot>
                <x-slot name="description">{{ $paidForPaper ? ($id ? 'Letter of Acceptance tersedia dan pembayaran Anda sudah lunas.' : 'Your Letter of Acceptance is available and your payment is complete.') : ($id ? 'Letter of Acceptance sudah tersedia. Selesaikan pembayaran registrasi presenter untuk mengunci slot Anda.' : 'Your Letter of Acceptance is available. Complete the presenter registration payment to secure your slot.') }}</x-slot>
                <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-gray-500">{{ $id ? 'Target jurnal:' : 'Journal target:' }}</span>
                    <x-filament::badge :color="$record->journal_target === 'sinta3' ? 'warning' : 'gray'">{{ $record->journalTargetLabel() }}</x-filament::badge>
                    @if($record->sinta3_offered && $record->journal_target !== 'sinta3' && ! $record->sinta3OfferIsLocked())
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
            <x-filament::section icon="heroicon-o-document-arrow-up" icon-color="primary">
                <x-slot name="heading">{{ $id ? 'Naskah final' : 'Final manuscript' }}</x-slot>
                <x-slot name="description">
                    {{ $id ? 'Setelah abstract diterima, siapkan berkas naskah final sesuai template jurnal tujuan Anda.' : 'After your abstract is accepted, prepare the final manuscript using the template for your selected journal.' }}
                </x-slot>

                <div class="divide-y divide-gray-200">
                    <div class="pb-5">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-950">{{ $id ? '1. Unduh template' : '1. Download the template' }}</h3>
                                <p class="mt-1 text-sm text-gray-600">
                                    {{ $id ? 'Target jurnal:' : 'Journal target:' }}
                                    <strong class="text-gray-900">{{ $templateChoiceReady ? $record->journalTargetLabel() : ($id ? 'belum dipilih' : 'not selected yet') }}</strong>
                                </p>
                            </div>
                            @if($templateUrl)
                                <x-filament::button tag="a" color="gray" outlined icon="heroicon-o-arrow-down-tray" href="{{ $templateUrl }}" target="_blank" rel="noopener noreferrer">
                                    {{ $id ? 'Unduh template' : 'Download template' }}
                                </x-filament::button>
                            @endif
                        </div>
                        @if(! $templateChoiceReady)
                            <p class="mt-3 text-sm text-gray-600">{{ $id ? 'Pilih target jurnal di halaman pembayaran. Template yang sesuai akan tampil otomatis di sini.' : 'Choose a journal target on the payment page. Its template will appear here automatically.' }}</p>
                        @elseif(! $templateUrl)
                            <p class="mt-3 text-sm text-gray-600">{{ $id ? 'Template untuk jurnal ini belum tersedia. Hubungi panitia sebelum menyiapkan naskah.' : 'The template for this journal is not available yet. Contact the committee before preparing your manuscript.' }}</p>
                        @endif
                    </div>

                    <div class="pt-5">
                        <h3 class="text-sm font-semibold text-gray-950">{{ $id ? '2. Unggah naskah final' : '2. Upload the final manuscript' }}</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ $id ? 'Gunakan template di atas; maksimal 20 MB.' : 'Use the template above; maximum 20 MB.' }}</p>
                        {{-- Panitia menyunting naskah untuk penerbitan, jadi full paper
                             wajib dalam format Word yang bisa disunting. --}}
                        <div class="mt-3 flex items-start gap-2 rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800">
                            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-warning-600" />
                            <span>
                                <strong>{{ $id ? 'Full paper wajib berupa file Word (.docx).' : 'The full paper must be a Word file (.docx).' }}</strong>
                                {{ $id ? 'File PDF atau .doc tidak dapat diunggah.' : 'PDF or .doc files cannot be uploaded.' }}
                            </span>
                        </div>

                        @if($record->hasFullPaper())
                            <div class="mt-4 flex flex-wrap items-center gap-3 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm">
                                <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5 shrink-0 text-success-600" />
                                <span class="min-w-0 flex-1 text-success-800">
                                    {{ $id ? 'Naskah sudah terkirim' : 'Manuscript submitted' }}: <strong class="break-all">{{ $record->fullPaperMedia()?->file_name }}</strong>
                                    @if($record->full_paper_submitted_at)· {{ $record->full_paper_submitted_at->format('d M Y, H:i') }}@endif
                                </span>
                                <a class="font-medium text-primary-700 hover:underline" href="{{ route('author.submissions.full-paper.download', $record) }}">{{ $id ? 'Unduh file' : 'Download file' }}</a>
                            </div>
                        @endif

                        @if(! $paidForPaper)
                            <p class="mt-4 text-sm text-gray-600">{{ $id ? 'Unggah file tersedia setelah pembayaran registrasi terverifikasi.' : 'File upload becomes available after your registration payment is verified.' }}</p>
                        @elseif(! $fullPaperOpen)
                            <p class="mt-4 text-sm text-gray-600">{{ $id ? 'Tenggat unggah naskah final telah berakhir. Hubungi panitia bila perlu bantuan.' : 'The final manuscript deadline has passed. Contact the committee if you need help.' }}</p>
                        @else
                            <form method="POST" action="{{ route('author.submissions.full-paper', $record) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                                @csrf
                                <label for="full-paper-{{ $record->id }}" class="block text-sm font-medium text-gray-900">{{ $record->hasFullPaper() ? ($id ? 'Pilih file pengganti' : 'Choose a replacement file') : ($id ? 'Pilih file naskah final' : 'Choose your final manuscript file') }}</label>
                                <input id="full-paper-{{ $record->id }}" type="file" name="full_paper" required accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                       class="block w-full max-w-xl rounded-lg border border-gray-300 bg-white text-sm text-gray-700 file:mr-4 file:border-0 file:border-r file:border-gray-300 file:bg-gray-50 file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-gray-900 hover:file:bg-gray-100" />
                                @error('full_paper')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror
                                <div>
                                    <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray">
                                        {{ $record->hasFullPaper() ? ($id ? 'Unggah file pengganti' : 'Upload replacement file') : ($id ? 'Kirim naskah final' : 'Submit final manuscript') }}
                                    </x-filament::button>
                                </div>
                            </form>
                        @endif
                    </div>
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

                @if(filled($record->abstract))
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
