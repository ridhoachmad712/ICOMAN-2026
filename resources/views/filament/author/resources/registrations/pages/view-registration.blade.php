<x-filament-panels::page>
    @php
        $id = app()->getLocale() === 'id';
        $record->loadMissing(['registrationFee', 'submission', 'payments']);

        $price = $record->priceDetails();
        $currency = $price['currency'];
        $money = fn ($value) => $currency.' '.number_format((float) $value, 0, ',', '.');

        $statusColor = match($record->status) {'paid'=>'success','failed'=>'danger','pending_verification'=>'warning',default=>'gray'};
        $statusLabel = $id ? match($record->status) {
            'pending' => 'Belum dibayar', 'pending_verification' => 'Menunggu verifikasi',
            'paid' => 'Lunas', 'failed' => 'Gagal', default => ucwords(str_replace('_', ' ', $record->status)),
        } : ucwords(str_replace('_', ' ', $record->status));

        // Opsi jurnal hanya relevan selama tagihan belum lunas.
        $canChooseJournal = (bool) ($record->submission?->sinta3_offered && ! ($price['legacy'] ?? false) && ! $record->hasUnresolvedPayment() && in_array($record->status, ['pending', 'failed'], true));
        $sinta3Fee = (int) $price['quoted_addon_amount'];
        $basePrice = (float) $price['base_amount'];
        $isSinta = $price['journal_target'] === 'sinta3';

        $discount = (float) ($price['discount_amount'] ?? 0);
        // Kotak voucher hanya muncul bila memang masih bisa dipakai: jalur
        // presenter, tagihan belum selesai, dan belum ada voucher terpakai.
        $canRedeemVoucher = $record->submission_id !== null
            && $record->voucher_id === null
            && ! ($price['legacy'] ?? false)
            && ! $record->hasUnresolvedPayment()
            && in_array($record->status, ['pending', 'failed'], true);
    @endphp

    <div class="space-y-6">
        <x-author-flash />
        @if(($price['source_currency'] ?? 'IDR') === 'USD')
            <p class="text-sm">{{ $id ? 'Harga asal' : 'Listed price' }}: USD {{ number_format((float) $price['source_amount'], 2) }}.
                {{ $id ? 'Kurs tetap pada invoice ini' : 'Exchange rate fixed for this invoice' }}: IDR {{ number_format((float) $price['exchange_rate'], 0, ',', '.') }} / USD.</p>
        @endif
        @if($record->status === 'pending_verification')
            <div role="status" class="rounded-xl bg-amber-50 p-4 text-amber-900">{{ $id ? 'Pembayaran memerlukan rekonsiliasi panitia. Jangan membayar ulang. Hubungi panitia dengan nomor invoice ini.' : 'Your payment requires committee reconciliation. Do not pay again. Contact the committee with this invoice number.' }}</div>
        @endif
        @if($record->payments->isNotEmpty() && $record->status !== 'paid')
            <form method="POST" action="{{ route('author.registration.sync', $record) }}">@csrf
                <x-filament::button type="submit" color="gray">{{ $id ? 'Periksa Status Pembayaran' : 'Check Payment Status' }}</x-filament::button>
            </form>
        @endif
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
                        <dt class="text-gray-500">{{ $price['category'][app()->getLocale()] ?? $price['category']['en'] ?? 'Registration' }}</dt>
                        <dd class="shrink-0 font-medium text-gray-950 dark:text-white">{{ $money($basePrice) }}</dd>
                    </div>

                    @if($isSinta)
                        <div class="flex items-start justify-between gap-4 text-warning-700 dark:text-warning-400">
                            <dt>{{ $id ? 'Tambahan penerbitan Jurnal SINTA 3' : 'SINTA 3 journal publication add-on' }}</dt>
                            <dd class="shrink-0 font-semibold">+ {{ $money($price['addon_amount']) }}</dd>
                        </div>
                    @endif

                    @if($discount > 0)
                        <div class="flex items-start justify-between gap-4 text-success-700 dark:text-success-400">
                            <dt>{{ $id ? 'Voucher co-host' : 'Co-host voucher' }} @if(! empty($price['voucher_code']))<span class="font-mono text-xs">({{ $price['voucher_code'] }})</span>@endif</dt>
                            <dd class="shrink-0 font-semibold">− {{ $money($discount) }}</dd>
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
                        <p class="mt-1 font-medium text-gray-950 dark:text-white">{{ $record->isWaived() ? ($id ? 'Voucher co-host' : 'Co-host voucher') : 'Kasera Pay' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $id ? 'Abstrak' : 'Abstract' }}</p>
                        @if($record->submission)
                            <x-filament::link class="mt-1" href="{{ \App\Filament\Author\Resources\Papers\PaperResource::getUrl('view', ['record' => $record->submission]) }}">{{ $id ? 'Abstrak ' : 'Abstract ' }}{{ $record->submission->submission_number }}</x-filament::link>
                        @else
                            <p class="mt-1 font-medium text-gray-950 dark:text-white">{{ $id ? 'Peserta seminar' : 'Seminar participant' }}</p>
                        @endif
                    </div>
                </div>

                @if($canRedeemVoucher)
                    {{-- Sengaja di bawah pilihan jurnal: begitu voucher menutup
                         seluruh tagihan, registrasi lunas dan opsi jurnal terkunci. --}}
                    <div class="mt-5 border-t border-gray-200 pt-4 dark:border-white/10" x-data="{ open: false }">
                        <button type="button" x-show="! open" x-on:click="open = true"
                                class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                            {{ $id ? 'Punya kode voucher co-host?' : 'Have a co-host voucher code?' }}
                        </button>

                        <div x-show="open" x-cloak>
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $id ? 'Kode voucher co-host' : 'Co-host voucher code' }}</p>
                            <p class="mt-1 text-xs leading-relaxed text-gray-500">
                                {{ $id
                                    ? 'Voucher membebaskan biaya registrasi dasar. Bila Anda memilih penerbitan Jurnal SINTA 3, biaya tambahannya tetap dibayar. Tentukan pilihan jurnal lebih dulu — setelah tagihan lunas, pilihan itu tidak bisa diubah sendiri.'
                                    : 'The voucher waives your base registration fee. If you choose SINTA 3 publication, its add-on remains payable. Settle your journal choice first — once the invoice is paid you cannot change it yourself.' }}
                            </p>

                            <form method="POST" action="{{ route('author.registration.voucher', $record) }}" class="mt-3 flex flex-wrap items-start gap-2" x-data="{ submitting: false }" @submit="submitting = true">
                                @csrf
                                <input type="text" name="voucher_code" required maxlength="40" autocomplete="off"
                                       placeholder="{{ $id ? 'Masukkan kode' : 'Enter code' }}"
                                       class="min-w-0 flex-1 rounded-lg border-gray-300 font-mono text-sm uppercase shadow-sm placeholder:font-sans placeholder:normal-case focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white">
                                <x-filament::button type="submit" color="gray" x-bind:disabled="submitting">
                                    {{ $id ? 'Pakai Voucher' : 'Apply Voucher' }}
                                </x-filament::button>
                            </form>
                        </div>
                    </div>
                @endif

                @if($record->status === 'paid')
                    <div class="mt-5 rounded-xl border border-success-200 bg-success-50 p-4 dark:border-success-500/20 dark:bg-success-500/10">
                        <p class="text-sm font-semibold text-success-800 dark:text-success-300">
                            {{ $record->isWaived()
                                ? ($id ? 'Registrasi dibebaskan voucher co-host' : 'Registration waived by co-host voucher')
                                : ($id ? 'Pembayaran terverifikasi' : 'Payment verified') }}
                        </p>
                        <p class="mt-1 text-xs leading-relaxed text-success-700 dark:text-success-400">
                            {{ $record->submission
                                ? ($id ? 'Registrasi presenter dan akses seminar Anda sudah aktif.' : 'Your presenter registration and seminar access are active.')
                                : ($id ? 'Akses seminar Anda sudah aktif.' : 'Your seminar access is active.') }}
                            @if($record->paid_at) · {{ $record->paid_at->format('d M Y, H:i') }} @endif
                        </p>
                    </div>
                @elseif($record->status !== 'pending_verification')
                    @php
                        $canInstall = $record->allowsInstallments();
                        $firstAmount = $record->firstInstallmentAmount();
                        $secondAmount = (float) $record->amount - $firstAmount;
                        $installmentDue = $record->installmentDueAt();
                        $dueNow = $record->amountDueNow();
                        $card = 'flex h-full flex-col rounded-xl border p-4';
                        $gatewayNote = $id
                            ? 'Anda akan diarahkan ke halaman pembayaran aman Kasera Pay untuk memilih QRIS, virtual account, transfer bank, atau dompet digital.'
                            : 'You will be redirected to Kasera Pay secure checkout to choose QRIS, virtual account, bank transfer, or an e-wallet.';
                    @endphp

                    <div class="mt-5 border-t border-gray-200 pt-5 dark:border-white/10">
                        @if($record->isPartiallyPaid())
                            {{-- Cicilan berjalan: yang tersisa hanya satu langkah, jadi tidak ada pilihan lagi. --}}
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $id ? 'Cicilan 1 dari 2 sudah lunas' : 'Instalment 1 of 2 settled' }}
                            </p>

                            <ol class="mt-3 space-y-2 text-sm">
                                <li class="flex items-center justify-between gap-3 rounded-lg bg-success-50 px-3 py-2 dark:bg-success-500/10">
                                    <span class="flex items-center gap-2 text-success-800 dark:text-success-300">
                                        <x-filament::icon icon="heroicon-m-check-circle" class="h-5 w-5" />
                                        {{ $id ? 'Cicilan pertama' : 'First instalment' }}
                                    </span>
                                    <span class="font-semibold text-success-800 dark:text-success-300">{{ $money($record->paidAmount()) }}</span>
                                </li>
                                <li class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                                    <span class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                                        <x-filament::icon icon="heroicon-m-clock" class="h-5 w-5" />
                                        {{ $id ? 'Sisa yang harus dilunasi' : 'Still to settle' }}
                                        @if($installmentDue)
                                            <span class="text-xs text-gray-500">· {{ $id ? 'sebelum' : 'by' }} {{ $installmentDue->format('d M Y') }}</span>
                                        @endif
                                    </span>
                                    <span class="font-semibold text-gray-950 dark:text-white">{{ $money($record->outstandingAmount()) }}</span>
                                </li>
                            </ol>

                            <p class="mt-3 text-xs leading-relaxed text-gray-500">
                                {{ $id
                                    ? 'Registrasi Anda aktif setelah sisa ini lunas. Unggah full paper terbuka setelah itu.'
                                    : 'Your registration becomes active once this balance is settled. Full paper upload opens then.' }}
                            </p>

                            <form method="POST" action="{{ route('author.registration.pay', $record) }}" class="mt-4" x-data="{ submitting: false }" @submit="submitting = true">
                                @csrf
                                <x-filament::button type="submit" x-bind:disabled="submitting" icon="heroicon-m-arrow-right" icon-position="after" class="w-full justify-center">
                                    {{ $id ? 'Lunasi Sisa' : 'Settle the Balance' }} ({{ $money($dueNow) }})
                                </x-filament::button>
                            </form>

                        @elseif($canInstall)
                            {{-- Dua cara membayar, ditampilkan setara supaya pilihannya jelas
                                 dan bukan terbaca sebagai catatan kaki di bawah satu tombol. --}}
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $id ? 'Pilih cara pembayaran' : 'Choose how to pay' }}
                            </p>

                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <div class="{{ $card }} border-primary-300 bg-primary-50/40 dark:border-primary-500/30 dark:bg-primary-500/5">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-400">
                                        {{ $id ? 'Bayar lunas' : 'Pay in full' }}
                                    </p>
                                    <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $money($record->amount) }}</p>
                                    <p class="mt-2 grow text-xs leading-relaxed text-gray-600 dark:text-gray-400">
                                        {{ $id
                                            ? 'Sekali bayar. Registrasi Anda langsung aktif dan unggah full paper terbuka.'
                                            : 'One payment. Your registration is active straight away and full paper upload opens.' }}
                                    </p>
                                    <form method="POST" action="{{ route('author.registration.pay', $record) }}" class="mt-4" x-data="{ submitting: false }" @submit="submitting = true">
                                        @csrf
                                        <x-filament::button type="submit" x-bind:disabled="submitting" class="w-full justify-center">
                                            {{ $id ? 'Bayar Lunas' : 'Pay in Full' }}
                                        </x-filament::button>
                                    </form>
                                </div>

                                <div class="{{ $card }} border-gray-200 dark:border-white/10">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        {{ $id ? 'Bayar dua tahap' : 'Pay in two instalments' }}
                                    </p>
                                    <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                                        {{ $money($firstAmount) }}
                                        <span class="text-sm font-normal text-gray-500">{{ $id ? 'sekarang' : 'now' }}</span>
                                    </p>
                                    <p class="mt-2 grow text-xs leading-relaxed text-gray-600 dark:text-gray-400">
                                        {{ $id ? 'Lalu' : 'Then' }} <strong>{{ $money($secondAmount) }}</strong>@if($installmentDue) {{ $id ? 'paling lambat' : 'by' }} {{ $installmentDue->format('d M Y') }}@endif.
                                        {{ $id ? 'Totalnya sama' : 'The total is the same' }} ({{ $money($record->amount) }}).
                                        {{ $id
                                            ? 'Registrasi baru aktif setelah cicilan kedua, jadi unggah full paper terbuka setelah itu.'
                                            : 'Your registration only becomes active after the second instalment, so full paper upload opens then.' }}
                                    </p>
                                    <form method="POST" action="{{ route('author.registration.pay', $record) }}" class="mt-4" x-data="{ submitting: false }" @submit="submitting = true">
                                        @csrf
                                        <input type="hidden" name="plan" value="installment">
                                        <x-filament::button type="submit" color="gray" x-bind:disabled="submitting" class="w-full justify-center">
                                            {{ $id ? 'Bayar Cicilan Pertama' : 'Pay First Instalment' }}
                                        </x-filament::button>
                                    </form>
                                </div>
                            </div>

                        @else
                            <form method="POST" action="{{ route('author.registration.pay', $record) }}" x-data="{ submitting: false }" @submit="submitting = true">
                                @csrf
                                <x-filament::button type="submit" x-bind:disabled="submitting" icon="heroicon-m-arrow-right" icon-position="after" class="w-full justify-center">
                                    {{ $id ? 'Lanjutkan Pembayaran' : 'Continue to Payment' }} ({{ $money($dueNow) }})
                                </x-filament::button>
                            </form>
                        @endif

                        <p class="mt-3 text-xs leading-relaxed text-gray-500">{{ $gatewayNote }}</p>
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
