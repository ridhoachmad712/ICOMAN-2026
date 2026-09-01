<x-filament-panels::page>
    @php
        $id = app()->getLocale() === 'id';
        $paperResource = \App\Filament\Author\Resources\Papers\PaperResource::class;
        $registrationResource = \App\Filament\Author\Resources\Registrations\RegistrationResource::class;
        $profilePage = \App\Filament\Author\Pages\AuthorProfile::class;
        $statusLabel = fn (string $status) => ($id ? [
            'extended_abstract_draft' => 'Draft abstract',
            'abstract_submitted' => 'Abstrak terkirim', 'abstract_under_review' => 'Abstrak direview',
            'abstract_approved' => 'Lolos review', 'extended_abstract_submitted' => 'Abstract terkirim',
            'extended_abstract_under_review' => 'Verifikasi reviewer', 'accepted' => 'Accepted',
            'rejected' => 'Tidak lolos', 'pending' => 'Belum dibayar',
            'pending_verification' => 'Menunggu verifikasi', 'paid' => 'Lunas', 'failed' => 'Gagal',
        ][$status] ?? ucwords(str_replace('_', ' ', $status)) : ucwords(str_replace('_', ' ', $status)));
        $statusColor = fn (string $status) => match($status) {
            'accepted', 'paid' => 'success', 'rejected', 'failed' => 'danger',
            'extended_abstract_submitted', 'pending', 'pending_verification' => 'warning', default => 'info',
        };
    @endphp

    <div class="space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <x-filament::badge color="primary">
                        {{ $author->isParticipant() ? ($id ? 'Peserta seminar' : 'Seminar participant') : ($id ? 'Pemakalah' : 'Presenter') }}
                    </x-filament::badge>
                    @if($author->isPresenter())<x-filament::badge color="success">{{ $id ? 'Akses seminar termasuk' : 'Seminar access included' }}</x-filament::badge>@endif
                    @if(currentEdition())<span class="text-sm text-gray-500">{{ currentEdition()->name }}</span>@endif
                </div>
                <h2 class="text-xl font-semibold text-gray-950 sm:text-2xl">{{ $id ? 'Selamat datang, '.$author->name : 'Welcome, '.$author->name }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ $id ? 'Lanjutkan dari langkah yang paling membutuhkan perhatian Anda.' : 'Continue from the step that needs your attention most.' }}</p>
            </div>
            <x-filament::button tag="a" color="gray" outlined icon="heroicon-o-user" href="{{ $profilePage::getUrl(panel: 'author') }}">
                {{ $id ? 'Profil saya' : 'My profile' }}
            </x-filament::button>
        </header>

        <section class="rounded-xl border border-primary-200 bg-white p-5 sm:p-6">
            <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                <div class="max-w-2xl">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wider text-primary-700">{{ $id ? 'Langkah berikutnya' : 'Next step' }}</p>
                        <x-filament::badge :color="$nextAction['actor']['color']" :icon="$nextAction['actor']['icon']">
                            {{ $nextAction['actor']['label'] }}
                        </x-filament::badge>
                    </div>
                    <h3 class="mt-2 text-lg font-semibold text-gray-950 sm:text-xl">{{ $nextAction['title'] }}</h3>
                    <p class="mt-1.5 text-sm leading-6 text-gray-600">{{ $nextAction['description'] }}</p>

                    @if($nextAction['deadline'])
                        <div class="mt-4 flex items-start gap-2 text-sm">
                            <x-filament::icon icon="heroicon-o-calendar-days" class="mt-0.5 h-5 w-5 shrink-0 text-gray-400" />
                            <p>
                                <span class="font-medium text-gray-800">{{ $nextAction['deadline']['label'] }}</span>
                                <span class="text-gray-500"> · {{ $nextAction['deadline']['date']->format('d M Y') }} · {{ $nextAction['deadline']['relative'] }}</span>
                            </p>
                        </div>
                    @endif
                </div>
                <x-filament::button tag="a" href="{{ $nextAction['route'] }}" icon="heroicon-m-arrow-right" icon-position="after" class="shrink-0">
                    {{ $nextAction['label'] }}
                </x-filament::button>
            </div>
            <div class="mt-5 border-t border-gray-100 pt-4">
                <div class="mb-2 flex items-center justify-between gap-3 text-xs">
                    <span class="font-medium text-gray-600">{{ $id ? 'Progres tahapan utama' : 'Main journey progress' }}</span>
                    <span class="font-semibold text-gray-950">{{ $nextAction['progress'] }}%</span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-primary-500" style="width: {{ $nextAction['progress'] }}%"></div></div>
            </div>
        </section>

        <x-filament::section>
            <x-slot name="heading">{{ $author->isPresenter() ? ($id ? 'Tahapan submission paper' : 'Paper submission stages') : ($id ? 'Tahapan peserta seminar' : 'Seminar participant stages') }}</x-slot>
            <x-slot name="description">{{ $id ? 'Tahap aktif menunjukkan siapa yang perlu bertindak. Tanggal selesai tersimpan sebagai riwayat progres.' : 'The active stage shows who needs to act. Completion dates are retained as your progress history.' }}</x-slot>

            @if($author->isPresenter())
                <div class="author-policy-note">
                    <x-filament::icon icon="heroicon-o-information-circle" class="h-5 w-5 shrink-0" />
                    <span>{{ $id ? 'Batas submission: satu akun hanya dapat mengirim satu paper pada edisi ini.' : 'Submission limit: each account may submit only one paper in this edition.' }}</span>
                </div>
            @endif

            @include('filament.author.components.journey-timeline', ['steps' => $journeySteps])
        </x-filament::section>

        @if($submissions->isNotEmpty() || $showPayments)
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                @if($author->isPresenter() && $submissions->isNotEmpty())
                    <x-filament::section>
                        <x-slot name="heading">{{ $id ? 'Paper saya' : 'My paper' }}</x-slot>
                        <x-slot name="description">{{ $id ? 'Status paper dan hasil review terbaru.' : 'Your paper status and latest review result.' }}</x-slot>
                        <div class="divide-y divide-gray-200">
                            @foreach($submissions as $submission)
                                <a href="{{ $paperResource::getUrl($paperResource::canEdit($submission) ? 'extended-abstract' : 'view', ['record' => $submission], panel: 'author') }}" class="author-record-row -mx-2 flex flex-col gap-3 px-2 py-4 first:pt-1 last:pb-1 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0"><p class="truncate text-sm font-semibold text-gray-950">{{ $submission->title }}</p><p class="mt-1 text-xs text-gray-500">Paper #{{ str_pad((string) $submission->id, 5, '0', STR_PAD_LEFT) }} · {{ $submission->submitted_at?->format('d M Y') }}</p></div>
                                    <x-filament::badge :color="$statusColor($submission->status)" class="self-start sm:self-auto">{{ $statusLabel($submission->status) }}</x-filament::badge>
                                </a>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif

                @if($showPayments)
                <x-filament::section>
                    <x-slot name="heading">{{ $id ? 'Registrasi & pembayaran' : 'Registration & payment' }}</x-slot>
                    <x-slot name="description">{{ $id ? 'Invoice dan status pembayaran terbaru.' : 'Your latest invoices and payment status.' }}</x-slot>
                    <x-slot name="afterHeader"><x-filament::link href="{{ $registrationResource::getUrl(panel: 'author') }}">{{ $id ? 'Lihat semua' : 'View all' }}</x-filament::link></x-slot>
                    <div class="divide-y divide-gray-200">
                        @forelse($registrations->take(3) as $registration)
                            <a href="{{ $registrationResource::getUrl('view', ['record' => $registration], panel: 'author') }}" class="author-record-row -mx-2 flex flex-col gap-3 px-2 py-4 first:pt-1 last:pb-1 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0"><p class="text-sm font-semibold text-gray-950">{{ $registration->registrationFee?->category }}</p><p class="mt-1 text-xs text-gray-500">Invoice #{{ str_pad((string) $registration->id, 5, '0', STR_PAD_LEFT) }} · IDR {{ number_format((float) $registration->amount, 0, ',', '.') }}</p></div>
                                <x-filament::badge :color="$statusColor($registration->status)" class="self-start sm:self-auto">{{ $statusLabel($registration->status) }}</x-filament::badge>
                            </a>
                        @empty
                            <div class="py-6 text-center"><p class="text-sm text-gray-500">{{ $id ? 'Belum ada registrasi.' : 'No registrations yet.' }}</p></div>
                        @endforelse
                    </div>
                </x-filament::section>
                @endif
            </div>

            <aside class="space-y-6">
                @if(count($recentUpdates))
                    <x-filament::section>
                        <x-slot name="heading">{{ $id ? 'Pemberitahuan terbaru' : 'Recent updates' }}</x-slot>
                        <x-slot name="description">{{ $id ? 'Perubahan penting pada paper Anda.' : 'Important changes to your paper.' }}</x-slot>
                        <div class="divide-y divide-gray-200">
                            @foreach($recentUpdates as $update)
                                <a href="{{ $update['route'] }}" class="author-update-row flex gap-3 py-4 first:pt-0 last:pb-0">
                                    <span class="author-update-icon is-{{ $update['color'] }}"><x-filament::icon :icon="$update['icon']" class="h-4 w-4" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-medium text-gray-950">{{ $update['title'] }}</span>
                                        <span class="mt-1 block text-xs leading-5 text-gray-500">{{ $update['description'] }}</span>
                                        <span class="mt-1 block text-xs text-gray-400">{{ $update['date']->format('d M Y, H:i') }}</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif

                <x-filament::section>
                    <x-slot name="heading">{{ $id ? 'Ringkasan' : 'Summary' }}</x-slot>
                    <dl class="divide-y divide-gray-200">
                        @if($author->isPresenter())
                            <div class="flex items-center justify-between gap-4 py-3 first:pt-0"><dt class="text-sm text-gray-600">{{ $id ? 'Kuota submission' : 'Submission quota' }}</dt><dd class="text-sm font-semibold text-gray-950">{{ $submissions->count() }}/1</dd></div>
                            <div class="flex items-center justify-between gap-4 py-3"><dt class="text-sm text-gray-600">{{ $id ? 'Akses seminar' : 'Seminar access' }}</dt><dd class="text-sm font-semibold text-success-700">{{ $id ? 'Termasuk' : 'Included' }}</dd></div>
                        @endif
                        @if($author->isParticipant() || $registrations->isNotEmpty())
                            <div class="flex items-center justify-between gap-4 py-3 last:pb-0"><dt class="text-sm text-gray-600">{{ $id ? 'Pembayaran lunas' : 'Paid registrations' }}</dt><dd class="text-sm font-semibold text-gray-950">{{ $registrations->where('status', 'paid')->count() }}</dd></div>
                        @endif
                    </dl>
                </x-filament::section>
            </aside>
        </div>
        @endif
    </div>
</x-filament-panels::page>
