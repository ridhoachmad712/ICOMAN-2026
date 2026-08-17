<x-author-layout :title="__('author.register_participant')">
    <div class="max-w-3xl mx-auto">
        <a href="{{ route('author.dashboard') }}" class="text-sm text-[var(--brand)] hover:underline">← {{ __('author.back_dashboard') }}</a>
        <h1 class="mt-2 text-2xl font-bold text-[var(--brand-2)]">{{ __('author.participant_registration') }}</h1>

        @if($fees->isEmpty())
            <div class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-slate-400">
                {{ __('author.no_fees') }}
            </div>
        @else
            <form method="POST" action="{{ route('author.registration.store') }}" class="mt-6 space-y-8" x-data="{ fee: '', method: '' }">
                @csrf

                <div>
                    <h2 class="font-semibold text-slate-800 mb-3">{{ __('author.step_category') }}</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach($fees as $fee)
                            @php $amount = $fee->price_early_bird ?? $fee->price_regular; @endphp
                            <label class="cursor-pointer rounded-xl border-2 p-4 transition"
                                   :class="fee === '{{ $fee->id }}' ? 'border-[var(--brand)] bg-[var(--brand)]/5' : 'border-slate-200'">
                                <input type="radio" name="registration_fee_id" value="{{ $fee->id }}" x-model="fee" class="sr-only" required>
                                <div class="font-semibold text-slate-800">{{ $fee->category }}</div>
                                <div class="mt-1 text-lg font-bold text-[var(--brand-2)]">{{ $fee->currency }} {{ number_format((float) $amount, 0, ',', '.') }}</div>
                                @if($fee->notes)<p class="mt-1 text-xs text-slate-500">{{ $fee->notes }}</p>@endif
                            </label>
                        @endforeach
                    </div>
                    @error('registration_fee_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <h2 class="font-semibold text-slate-800 mb-3">{{ __('author.step_method') }}</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="cursor-pointer rounded-xl border-2 p-4 transition"
                               :class="method === 'manual' ? 'border-[var(--brand)] bg-[var(--brand)]/5' : 'border-slate-200'">
                            <input type="radio" name="payment_method" value="manual" x-model="method" class="sr-only" required>
                            <div class="font-semibold text-slate-800">{{ __('author.manual_transfer') }}</div>
                            <p class="mt-1 text-xs text-slate-500">{{ __('author.manual_desc') }}</p>
                        </label>
                        <label class="cursor-pointer rounded-xl border-2 p-4 transition"
                               :class="method === 'gateway' ? 'border-[var(--brand)] bg-[var(--brand)]/5' : 'border-slate-200'">
                            <input type="radio" name="payment_method" value="gateway" x-model="method" class="sr-only" required>
                            <div class="font-semibold text-slate-800">{{ __('author.auto_payment') }}</div>
                            <p class="mt-1 text-xs text-slate-500">{{ __('author.auto_desc') }}</p>
                        </label>
                    </div>
                    @error('payment_method')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="btn btn-primary">{{ __('author.continue') }}</button>
            </form>
        @endif
    </div>
</x-author-layout>
