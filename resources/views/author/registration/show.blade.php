<x-author-layout :title="__('author.register_participant')">
    @php
        $s = siteSettings();
        $badge = match($registration->status) {
            'paid' => 'bg-emerald-100 text-emerald-700',
            'pending_verification' => 'bg-amber-100 text-amber-700',
            'failed' => 'bg-red-100 text-red-700',
            default => 'bg-slate-100 text-slate-700',
        };
        $proof = $registration->getFirstMediaUrl('payment_proof');
    @endphp

    <div class="max-w-3xl mx-auto">
        <a href="{{ route('author.dashboard') }}" class="text-sm text-[var(--brand)] hover:underline">← {{ __('author.back_dashboard') }}</a>

        @if(session('error'))
            <div class="mt-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        <div class="mt-2 flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-[var(--brand-2)]">{{ __('author.reg_number', ['num' => $registration->id]) }}</h1>
                <p class="text-slate-500">{{ $registration->registrationFee?->category }}</p>
            </div>
            <span class="inline-block rounded-full px-3 py-1 text-sm font-semibold {{ $badge }}">
                {{ ucwords(str_replace('_', ' ', $registration->status)) }}
            </span>
        </div>

        <div class="mt-2 text-2xl font-bold text-slate-900">
            IDR {{ number_format((float) $registration->amount, 0, ',', '.') }}
            <span class="text-sm font-normal text-slate-400">· {{ ucfirst($registration->payment_method) }}</span>
        </div>

        @if($registration->status === 'paid')
            <div class="mt-6 rounded-xl bg-emerald-50 border border-emerald-200 p-6 text-emerald-700">
                ✓ {{ __('author.paid_confirmed') }}
                @if($registration->paid_at)<span class="block text-sm">{{ __('author.paid_on') }}: {{ $registration->paid_at->format('d M Y H:i') }}</span>@endif
            </div>

        @elseif($registration->payment_method === 'manual')
            <div class="mt-6 card p-6">
                <h2 class="font-semibold text-slate-800">{{ __('author.manual_instructions') }}</h2>
                @if($s->bank_name || $s->bank_account_number)
                    <dl class="mt-3 space-y-1 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('author.bank') }}</dt><dd class="font-medium">{{ $s->bank_name ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('author.account_number') }}</dt><dd class="font-mono font-medium">{{ $s->bank_account_number ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('author.account_holder') }}</dt><dd class="font-medium">{{ $s->bank_account_holder ?? '—' }}</dd></div>
                        <div class="flex justify-between border-t pt-2 mt-2"><dt class="text-slate-500">{{ __('author.amount') }}</dt><dd class="font-bold">IDR {{ number_format((float) $registration->amount, 0, ',', '.') }}</dd></div>
                    </dl>
                @else
                    <p class="mt-2 text-sm text-slate-400">{{ __('author.bank_not_set') }}</p>
                @endif

                <div class="mt-5 border-t pt-5">
                    @if($proof)
                        <p class="text-sm text-emerald-600 mb-2">✓ {{ __('author.proof_uploaded') }} <a href="{{ $proof }}" target="_blank" class="underline">{{ __('author.view') }}</a></p>
                    @endif
                    <form method="POST" action="{{ route('author.registration.proof', $registration) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                        @csrf
                        <input type="file" name="proof" accept=".jpg,.jpeg,.png,.webp,.pdf" required
                               class="text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--brand)] file:px-4 file:py-2 file:text-white file:font-semibold">
                        <button type="submit" class="btn btn-primary text-sm">{{ $proof ? __('author.change_proof') : __('author.upload_proof') }}</button>
                    </form>
                    @error('proof')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

        @else
            <div class="mt-6 card p-6">
                <h2 class="font-semibold text-slate-800 mb-3">{{ __('author.auto_payment_title') }}</h2>
                <form method="POST" action="{{ route('author.registration.pay', $registration) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">{{ __('author.pay_now') }} →</button>
                </form>
            </div>
        @endif

        @if($registration->payments->isNotEmpty())
            <div class="mt-6 card overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100"><h2 class="font-semibold text-slate-800">{{ __('author.payment_history') }}</h2></div>
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-6 py-2">{{ __('author.col_method') }}</th><th class="px-6 py-2">{{ __('author.ref') }}</th><th class="px-6 py-2">{{ __('author.col_status') }}</th><th class="px-6 py-2">{{ __('author.time') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($registration->payments as $p)
                            <tr>
                                <td class="px-6 py-2">{{ ucfirst($p->method) }}{{ $p->gateway_name ? ' ('.$p->gateway_name.')' : '' }}</td>
                                <td class="px-6 py-2 font-mono text-xs">{{ $p->gateway_reference ?? '—' }}</td>
                                <td class="px-6 py-2">{{ ucfirst($p->status) }}</td>
                                <td class="px-6 py-2 text-slate-500">{{ $p->created_at->format('d M H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-author-layout>
