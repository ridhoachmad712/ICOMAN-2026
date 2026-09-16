@php
    $id = app()->getLocale() === 'id';
    $voucher = $coHost?->voucher;
    $money = fn ($value) => 'IDR '.number_format((float) $value, 0, ',', '.');
@endphp

<div class="space-y-6">
    <x-author-flash />

    {{-- Status pengajuan --}}
    <x-filament::section icon="heroicon-o-building-office-2" icon-color="primary">
        <x-slot name="heading">{{ $coHost?->institution_name }}</x-slot>
        <x-slot name="description">{{ $id ? 'Pengajuan co-host' : 'Co-host application' }}</x-slot>
        <x-slot name="afterHeader">
            <x-filament::badge :color="match($coHost?->status) { 'approved' => 'success', 'rejected' => 'danger', default => 'warning' }">
                {{ $coHost?->statusLabel() }}
            </x-filament::badge>
        </x-slot>

        @if($coHost?->isPending())
            <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ $id
                    ? 'Pengajuan Anda sedang ditinjau panitia. Kabarnya dikirim ke email penanggung jawab.'
                    : 'Your application is with the committee. The outcome will be sent to the contact person by email.' }}
            </p>
        @elseif($coHost?->status === 'rejected')
            <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ $id ? 'Pengajuan ini tidak disetujui.' : 'This application was not approved.' }}
                @if($coHost->rejection_reason) <span class="block mt-2">{{ $coHost->rejection_reason }}</span> @endif
            </p>
        @else
            <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ $id
                    ? 'Pengajuan disetujui. Kode voucher Anda terbit setelah biaya kemitraan lunas.'
                    : 'Your application is approved. Your voucher code is issued once the partnership fee is settled.' }}
            </p>
        @endif
    </x-filament::section>

    {{-- Invoice kemitraan --}}
    @if($coHostRegistration)
        <x-filament::section icon="heroicon-o-banknotes" icon-color="primary">
            <x-slot name="heading">{{ $id ? 'Biaya Kemitraan' : 'Partnership Fee' }}</x-slot>
            <x-slot name="afterHeader">
                <x-filament::badge :color="$coHostRegistration->status === 'paid' ? 'success' : 'warning'">
                    {{ $coHostRegistration->status === 'paid' ? ($id ? 'Lunas' : 'Paid') : ($id ? 'Belum dibayar' : 'Unpaid') }}
                </x-filament::badge>
            </x-slot>

            <p class="text-2xl font-bold text-gray-950 dark:text-white">{{ $money($coHostRegistration->amount) }}</p>

            @if($coHostRegistration->status !== 'paid')
                <form method="POST" action="{{ route('author.registration.pay', $coHostRegistration) }}" class="mt-4">
                    @csrf
                    <x-filament::button type="submit" icon="heroicon-o-arrow-right" icon-position="after">
                        {{ $id ? 'Lanjutkan Pembayaran' : 'Continue to Payment' }}
                    </x-filament::button>
                </form>
            @endif
        </x-filament::section>
    @endif

    {{-- Voucher & pemakaiannya --}}
    @if($voucher)
        <x-filament::section icon="heroicon-o-ticket" icon-color="primary">
            <x-slot name="heading">{{ $id ? 'Kode Voucher Anda' : 'Your Voucher Code' }}</x-slot>
            <x-slot name="description">
                {{ $id
                    ? 'Bagikan kode ini kepada penulis dari institusi Anda. Mereka memasukkannya saat pembayaran.'
                    : 'Share this code with authors from your institution. They enter it at payment.' }}
            </x-slot>

            <div class="flex flex-wrap items-center gap-4">
                {{-- Kode baru diperlihatkan setelah kemitraannya berjalan: sebelum
                     lunas kode itu tidak bisa dipakai, dan menampilkannya lebih
                     dulu hanya mengundang penulis mencobanya lalu ditolak. --}}
                @if($voucher->is_active)
                    <p class="font-mono text-2xl font-bold tracking-wide text-[var(--brand-2,#18315e)] dark:text-white">{{ $voucher->code }}</p>
                @else
                    <p class="font-mono text-2xl font-bold tracking-wide text-gray-400 dark:text-gray-600" aria-hidden="true">••••••••</p>
                    <p class="mt-1 text-xs leading-relaxed text-gray-500">
                        {{ $id
                            ? 'Kode terbit di sini segera setelah biaya kemitraan lunas.'
                            : 'The code appears here as soon as the partnership fee is settled.' }}
                    </p>
                @endif
                <x-filament::badge :color="$voucher->is_active ? 'success' : 'gray'">
                    {{ $voucher->is_active ? ($id ? 'Aktif' : 'Active') : ($id ? 'Menunggu pembayaran' : 'Awaiting payment') }}
                </x-filament::badge>
                <x-filament::badge color="info">
                    {{ $voucher->remainingSlots() }} / {{ $voucher->quota }} {{ $id ? 'slot tersisa' : 'slots left' }}
                </x-filament::badge>
            </div>

            @if($voucher->redemptions->isNotEmpty())
                <div class="mt-5 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10">
                                <th class="pb-3 pr-4">{{ $id ? 'Penulis' : 'Author' }}</th>
                                <th class="pb-3 pr-4">Email</th>
                                <th class="pb-3">{{ $id ? 'Dipakai' : 'Used' }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($voucher->redemptions as $redemption)
                                <tr>
                                    <td class="py-3 pr-4 text-gray-950 dark:text-white">{{ $redemption->author?->name ?? '—' }}</td>
                                    <td class="py-3 pr-4 text-gray-600 dark:text-gray-300">{{ $redemption->author?->email ?? '—' }}</td>
                                    <td class="py-3 text-gray-600 dark:text-gray-300">{{ $redemption->redeemed_at?->format('d M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="mt-5 text-sm text-gray-500">{{ $id ? 'Belum ada yang memakai kode ini.' : 'Nobody has used this code yet.' }}</p>
            @endif
        </x-filament::section>
    @endif
</div>
