<x-filament-panels::page>
    @php
        $id = app()->getLocale() === 'id';
        $record->loadMissing(['registrationFee', 'submission', 'payments']);
        $statusColor = match($record->status) {'paid'=>'success','failed'=>'danger','pending_verification'=>'warning',default=>'gray'};
        $statusLabel = $id ? match($record->status) {
            'pending' => 'Belum dibayar', 'pending_verification' => 'Menunggu verifikasi',
            'paid' => 'Lunas', 'failed' => 'Gagal', default => ucwords(str_replace('_', ' ', $record->status)),
        } : ucwords(str_replace('_', ' ', $record->status));
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">{{ $record->registrationFee?->category ?: ($id ? 'Registrasi Konferensi' : 'Conference Registration') }}</x-slot>
            <x-slot name="description">Invoice #{{ str_pad((string) $record->id, 5, '0', STR_PAD_LEFT) }} · {{ $record->created_at->format('d M Y, H:i') }}</x-slot>
            <x-slot name="afterHeader"><x-filament::badge :color="$statusColor">{{ $statusLabel }}</x-filament::badge></x-slot>

            <div class="grid gap-5 sm:grid-cols-3">
                <div><p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $id ? 'Total tagihan' : 'Total amount' }}</p><p class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $record->registrationFee?->currency ?: 'IDR' }} {{ number_format((float) $record->amount, 0, ',', '.') }}</p></div>
                <div><p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $id ? 'Metode' : 'Method' }}</p><p class="mt-1 text-sm font-medium text-gray-950 dark:text-white">Midtrans</p></div>
                <div><p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $id ? 'Abstrak' : 'Abstract' }}</p>@if($record->submission)<x-filament::link class="mt-1" href="{{ \App\Filament\Author\Resources\Papers\PaperResource::getUrl('view', ['record' => $record->submission]) }}">{{ $id ? 'Abstrak #' : 'Abstract #' }}{{ str_pad((string) $record->submission->id, 5, '0', STR_PAD_LEFT) }}</x-filament::link>@else<p class="mt-1 text-sm text-gray-950 dark:text-white">{{ $id ? 'Peserta seminar' : 'Seminar participant' }}</p>@endif</div>
            </div>
        </x-filament::section>

        @if($record->submission?->sinta3_offered && in_array($record->status, ['pending', 'failed'], true))
            @php
                $sinta3Fee = (int) rescue(fn () => siteSettings()->sinta3_fee, 0, false);
                $basePrice = (float) ($record->registrationFee?->currentPrice() ?? $record->amount);
                $isSinta = $record->submission->journal_target === 'sinta3';
            @endphp
            <x-filament::section icon="heroicon-o-academic-cap" icon-color="warning">
                <x-slot name="heading">{{ $id ? 'Opsi penerbitan jurnal' : 'Journal publication option' }}</x-slot>
                <x-slot name="description">{{ $id ? 'Paper Anda direkomendasikan untuk Jurnal SINTA 3. Pilih opsi penerbitan — total pembayaran menyesuaikan otomatis.' : 'Your paper is recommended for a SINTA 3 journal. Choose your publication option — the total adjusts automatically.' }}</x-slot>

                {{-- Pilihan langsung tersimpan begitu diklik; author tinggal lanjut membayar. --}}
                <form method="POST" action="{{ route('author.registration.journal', $record) }}" class="space-y-3">
                    @csrf @method('PATCH')
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 {{ ! $isSinta ? 'border-primary-400 bg-primary-50 dark:bg-primary-500/10' : 'border-gray-200 dark:border-white/10' }}">
                        <input type="radio" name="journal_target" value="regular" @checked(! $isSinta) onchange="this.form.submit()" class="mt-1">
                        <span>
                            <span class="block text-sm font-semibold text-gray-950 dark:text-white">{{ $id ? 'Jurnal Reguler' : 'Regular journal' }}</span>
                            <span class="block text-xs text-gray-500">{{ $id ? 'Tanpa biaya tambahan.' : 'No additional fee.' }} · IDR {{ number_format($basePrice, 0, ',', '.') }}</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 {{ $isSinta ? 'border-warning-400 bg-warning-50 dark:bg-warning-500/10' : 'border-gray-200 dark:border-white/10' }}">
                        <input type="radio" name="journal_target" value="sinta3" @checked($isSinta) onchange="this.form.submit()" class="mt-1">
                        <span>
                            <span class="block text-sm font-semibold text-gray-950 dark:text-white">{{ $id ? 'Jurnal SINTA 3' : 'SINTA 3 journal' }} <span class="text-warning-700">(+ IDR {{ number_format($sinta3Fee, 0, ',', '.') }})</span></span>
                            <span class="block text-xs text-gray-500">{{ $id ? 'Total menjadi' : 'Total becomes' }} <strong class="text-gray-950 dark:text-white">IDR {{ number_format($basePrice + $sinta3Fee, 0, ',', '.') }}</strong></span>
                        </span>
                    </label>
                </form>
            </x-filament::section>
        @endif

        @if($record->status === 'paid')
            <x-filament::section icon="heroicon-o-check-circle" icon-color="success">
                <x-slot name="heading">{{ $id ? 'Pembayaran terverifikasi' : 'Payment verified' }}</x-slot>
                <x-slot name="description">{{ $record->submission
                    ? ($id ? 'Registrasi presenter dan akses seminar Anda sudah aktif. Tidak perlu membeli paket peserta tambahan.' : 'Your presenter registration and seminar access are active. No additional attendee package is required.')
                    : ($id ? 'Akses seminar Anda sudah aktif. Tidak ada pembayaran lain yang diperlukan.' : 'Your seminar access is active. No further payment is required.') }}</x-slot>
                @if($record->paid_at)<p class="text-sm text-gray-600 dark:text-gray-300">{{ $id ? 'Terverifikasi pada' : 'Verified on' }} {{ $record->paid_at->format('d M Y, H:i') }}</p>@endif
            </x-filament::section>
        @else
            <x-filament::section icon="heroicon-o-bolt" icon-color="primary">
                <x-slot name="heading">{{ $id ? 'Bayar melalui Midtrans' : 'Pay with Midtrans' }}</x-slot>
                <x-slot name="description">{{ $id ? 'Anda akan diarahkan ke halaman pembayaran aman untuk memilih QRIS, virtual account, transfer bank, atau dompet digital.' : 'You will be redirected to a secure checkout to choose QRIS, virtual account, bank transfer, or an e-wallet.' }}</x-slot>
                <form method="POST" action="{{ route('author.registration.pay', $record) }}">@csrf<x-filament::button type="submit" icon="heroicon-m-arrow-right" icon-position="after">{{ $id ? 'Lanjutkan Pembayaran' : 'Continue to Payment' }}</x-filament::button></form>
            </x-filament::section>
        @endif

        <x-filament::section>
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
