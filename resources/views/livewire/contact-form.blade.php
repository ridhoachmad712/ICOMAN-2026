<div>
    @if($sent)
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-6 text-center">
            <p class="text-emerald-700 font-medium">{{ __('site.contact_success') }}</p>
            <button wire:click="$set('sent', false)" class="mt-3 text-sm text-emerald-600 hover:underline">＋ {{ __('site.contact_send') }}</button>
        </div>
    @else
        <form wire:submit="submit" class="space-y-4">
            {{-- Honeypot: disembunyikan dari user, diisi hanya oleh bot --}}
            <div class="hidden" aria-hidden="true">
                <label>Website<input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('site.contact_name') }}</label>
                <input type="text" wire:model="name"
                       class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)] shadow-sm">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('site.contact_email') }}</label>
                <input type="email" wire:model="email"
                       class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)] shadow-sm">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('site.contact_subject') }}</label>
                <input type="text" wire:model="subject"
                       class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)] shadow-sm">
                @error('subject') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('site.contact_message') }}</label>
                <textarea wire:model="message" rows="5"
                          class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)] shadow-sm"></textarea>
                @error('message') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <button type="submit"
                    class="btn btn-primary w-full disabled:opacity-50"
                    wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submit">{{ __('site.contact_send') }}</span>
                <span wire:loading wire:target="submit">…</span>
            </button>
        </form>
    @endif
</div>
