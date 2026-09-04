<x-filament-panels::page>
    @php
        $id = app()->getLocale() === 'id';
        $record->loadMissing(['registrationFee', 'submission', 'payments']);

        $currency = $record->registrationFee?->currency ?: 'IDR';
        $money = fn ($value) => $currency.' '.number_format((float) $value, 0, ',', '.');

        $statusColor = match($record->status) {'paid'=>'success','failed'=>'danger','pending_verification'=>'warning',default=>'gray'};
        $statusLabel = $id ? match($record->status) {
            'pending' => 'Belum dibayar', 'pending_verification' => 'Menunggu verifikasi',
            'paid' => 'Lunas', 'failed' => 'Gagal', default => ucwords(str_replace('_', ' ', $record->status)),
        } : ucwords(str_replace('_', ' ', $record->status));

        // Opsi jurnal hanya relevan selama tagihan belum lunas.
        $canChooseJournal = (bool) ($record->submission?->sinta3_offered && in_array($record->status, ['pending', 'failed'], true));
        $sinta3Fee = (int) rescue(fn () => siteSettings()->sinta3_fee, 0, false);
        $basePrice = (float) ($record->registrationFee?->currentPrice() ?? $record->amount);
        $isSinta = $record->submission?->journal_target === 'sinta3';
    @endphp

    <div class="space-y-6">
        {{-- Kabar baik lebih dulu: paper direkomendasikan ke SINTA 3. --}}
        @if($canChooseJournal)
            <div class="rounded-xl border border-warning-300 bg-warning-50 p-5 dark:border-warning-500/30 dark:bg-warning-500/10">
                <div class="flex items-start gap-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-warning-100 text-warning-600 dark:bg-warning-500/20">
                        <x-filament::icon icon="heroicon-o-sparkles" class="h-6 w-6" />
                    </span>
                    <div>
                        <p class="text-base font-bold text-warning-900 dark:text-warning-200">
                            {{ $id ? 'Selamat! Paper Anda berpeluang terbit di Jurnal SINTA 3' : 'Congratulations! Your paper has a chance to be published in a SINTA 3 journal' }}
                        </p>
                        <p class="mt-1 text-sm leading-relaxed text-warning-800 dark:text-warning-300">
                            {{ $id
                                ? 'Reviewer merekomendasikan naskah Anda untuk penerbitan pada jurnal terakreditasi SINTA 3. Silakan tentukan opsi penerbitan di samping — total tagihan menyesuaikan otomatis.'
                                : 'The reviewers recommended your manuscript for publication in a SINTA 3 accredited journal. Choose your publication option beside — your total adjusts automatically.' }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <div class="grid items-start gap-6 {{ $canChooseJournal ? 'lg:grid-cols-2' : '' }}">
            {{-- KIRI: pilihan penerbitan jurnal --}}
            @if($canChooseJournal)
                <x-filament::section icon="heroicon-o-academic-cap" icon-color="warning">
                    <x-slot name="heading">{{ $id ? 'Opsi penerbitan jurnal' : 'Journal publication option' }}</x-slot>
                    <x-slot name="description">{{ $id ? 'Pilih salah satu — tersimpan otomatis.' : 'Pick one — it saves automatically.' }}</x-slot>

                    {{-- Pilihan langsung tersimpan begitu diklik; author tinggal lanjut membayar. --}}
                    <form method="POST" action="{{ route('author.registration.journal', $record) }}" class="space-y-3">
                        @csrf @method('PATCH')
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition {{ ! $isSinta ? 'border-primary-400 bg-primary-50 dark:bg-primary-500/10' : 'border-gray-200 hover:border-gray-300 dark:border-white/10' }}">
                            <input type="radio" name="journal_target" value="regular" @checked(! $isSinta) onchange="this.form.submit()" class="mt-1">
                            <span>
                                <span class="block text-sm font-semibold text-gray-950 dark:text-white">{{ $id ? 'Jurnal Reguler' : 'Regular journal' }}</span>
                                <span class="block text-xs text-gray-500">{{ $id ? 'Tanpa biaya tambahan.' : 'No additional fee.' }}</span>
                                <span class="mt-1 block text-sm font-semibold text-gray-950 dark:text-white">{{ $money($basePrice) }}</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition {{ $isSinta ? 'border-warning-400 bg-warning-50 dark:bg-warning-500/10' : 'border-gray-200 hover:border-gray-300 dark:border-white/10' }}">
                            <input type="radio" name="journal_target" value="sinta3" @checked($isSinta) onchange="this.form.submit()" class="mt-1">
                            <span>
                                <span class="block text-sm font-semibold text-gray-950 dark:text-white">{{ $id ? 'Jurnal SINTA 3' : 'SINTA 3 journal' }}</span>
                                <span class="block text-xs text-warning-700 dark:text-warning-400">{{ $id ? 'Biaya penerbitan tambahan' : 'Additional publication fee' }} + {{ $money($sinta3Fee) }}</span>
                                <span class="mt-1 block text-sm font-semibold text-gray-950 dark:text-white">{{ $money($basePrice + $sinta3Fee) }}</span>
                            </span>
                        </label>
                    </form>
                </x-filament::section>
            @endif

            {{-- KANAN: informasi biaya --}}
            <x-filament::section icon="heroicon-o-banknotes" icon-color="primary">
                <x-slot name="heading">{{ $id ? 'Informasi Biaya' : 'Cost Information' }}</x-slot>
                <x-slot name="description">Invoice #{{ str_pad((string) $record->id, 5, '0', STR_PAD_LEFT) }} · {{ $record->created_at->format('d M Y, H:i') }}</x-slot>
                <x-slot name="afterHeader"><x-filament::badge :color="$statusColor">{{ $statusLabel }}</x-filament::badge></x-slot>

                <dl class="space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-gray-500">{{ $record->registrationFee?->category ?: ($id ? 'Paket registrasi' : 'Registration package') }}</dt>
                        <dd class="shrink-0 font-medium text-gray-950 dark:text-white">{{ $money($basePrice) }}</dd>
                    </div>

                    @if($isSinta)
                        <div class="flex items-start justify-between gap-4 text-warning-700 dark:text-warning-400">
                            <dt>{{ $id ? 'Tambahan penerbitan Jurnal SINTA 3' : 'SINTA 3 journal publication add-on' }}</dt>
                            <dd class="shrink-0 font-semibold">+ {{ $money($sinta3Fee) }}</dd>
                        </div>
                    @endif

                    <div class="flex items-center justify-between gap-4 border-t border-gray-200 pt-3 dark:border-white/10">
                        <dt class="font-semibold text-gray-950 dark:text-white">{{ $id ? 'Total tagihan' : 'Total amount' }}</dt>
                        <dd class="shrink-0 text-xl font-bold text-gray-950 dark:text-white">{{ $money($record->amount) }}</dd>
                    </div>
                </dl>

                <div class="mt-4 grid gap-3 border-t border-gray-200 pt-4 text-sm sm:grid-cols-2 dark:border-white/10">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $id ? 'Metode' : 'Method' }}</p>
                        <p class="mt-1 font-medium text-gray-950 dark:text-white">Midtrans</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $id ? 'Abstrak' : 'Abstract' }}</p>
                        @if($record->submission)
                            <x-filament::link class="mt-1" href="{{ \App\Filament\Author\Resources\Papers\PaperResource::getUrl('view', ['record' => $record->submission]) }}">{{ $id ? 'Abstrak #' : 'Abstract #' }}{{ str_pad((string) $record->submission->id, 5, '0', STR_PAD_LEFT) }}</x-filament::link>
                        @else
                            <p class="mt-1 font-medium text-gray-950 dark:text-white">{{ $id ? 'Peserta seminar' : 'Seminar participant' }}</p>
                        @endif
                    </div>
                </div>

                @if($record->status === 'paid')
                    <div class="mt-5 rounded-xl border border-success-200 bg-success-50 p-4 dark:border-success-500/20 dark:bg-success-500/10">
                        <p class="text-sm font-semibold text-success-800 dark:text-success-300">{{ $id ? 'Pembayaran terverifikasi' : 'Payment verified' }}</p>
                        <p class="mt-1 text-xs leading-relaxed text-success-700 dark:text-success-400">
                            {{ $record->submission
                                ? ($id ? 'Registrasi presenter dan akses seminar Anda sudah aktif.' : 'Your presenter registration and seminar access are active.')
                                : ($id ? 'Akses seminar Anda sudah aktif.' : 'Your seminar access is active.') }}
                            @if($record->paid_at) · {{ $record->paid_at->format('d M Y, H:i') }} @endif
                        </p>
                    </div>
                @else
                    <div class="mt-5 border-t border-gray-200 pt-4 dark:border-white/10">
                        <p class="mb-3 text-xs leading-relaxed text-gray-500">
                            {{ $id ? 'Anda akan diarahkan ke halaman pembayaran aman Midtrans untuk memilih QRIS, virtual account, transfer bank, atau dompet digital.' : 'You will be redirected to Midtrans secure checkout to choose QRIS, virtual account, bank transfer, or an e-wallet.' }}
                        </p>
                        <form method="POST" action="{{ route('author.registration.pay', $record) }}">
                            @csrf
                            <x-filament::button type="submit" icon="heroicon-m-arrow-right" icon-position="after" class="w-full justify-center">
                                {{ $id ? 'Lanjutkan Pembayaran' : 'Continue to Payment' }}
                            </x-filament::button>
                        </form>
                    </div>
                @endif
            </x-filament::section>
        </div>

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">{{ $id ? 'Riwayat Pembayaran' : 'Payment History' }}</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10"><th class="pb-3 pr-4">{{ $id ? 'Waktu' : 'Time' }}</th><th class="pb-3 pr-4">{{ $id ? 'Metode' : 'Method' }}</th><th class="pb-3 pr-4">Reference</th><th class="pb-3">Status</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse($record->payments->sortByDesc('created_at') as $payment)<tr><td class="py-3 pr-4 text-gray-600 dark:text-gray-300">{{ $payment->created_at->format('d M Y, H:i') }}</td><td class="py-3 pr-4 text-gray-950 dark:text-white">{{ ucfirst($payment->method) }}</td><td class="py-3 pr-4 font-mono text-xs text-gray-500">{{ $payment->gateway_reference ?: '—' }}</td><td class="py-3"><x-filament::badge :color="$payment->status === 'success' ? 'success' : ($payment->status === 'failed' ? 'danger' : 'gray')">{{ ucfirst($payment->status) }}</x-filament::badge></td></tr>@empty<tr><td colspan="4" class="py-4 text-gray-500">{{ $id ? 'Belum ada percobaan pembayaran.' : 'No payment attempts yet.' }}</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
