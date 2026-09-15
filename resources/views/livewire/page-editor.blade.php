<div
    x-data="{ panel: @entangle('editing') }"
    x-on:page-section-changed.window="setTimeout(() => window.location.reload(), 150)"
>
    {{-- Bar mode sunting --}}
    <div class="fixed inset-x-0 top-0 z-[60] flex items-center justify-between gap-4 bg-[var(--brand-2)] px-4 py-2.5 text-xs text-white shadow-lg">
        <span class="font-semibold tracking-wide uppercase">Mode sunting halaman</span>
        <div class="flex items-center gap-3">
            <span class="hidden text-white/70 sm:inline">Klik teks judul untuk mengubahnya langsung.</span>
            <a href="{{ url()->current() }}" class="rounded-md bg-white/15 px-3 py-1.5 font-semibold hover:bg-white/25">Selesai</a>
        </div>
    </div>

    {{-- Panel pengaturan blok --}}
    <div x-show="panel" x-cloak class="fixed inset-y-0 right-0 z-[70] w-full max-w-sm overflow-y-auto bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <p class="text-sm font-semibold text-slate-900">Pengaturan blok</p>
            <button type="button" wire:click="closeEditor" class="text-slate-400 hover:text-slate-700">&times;</button>
        </div>

        <div class="space-y-4 px-5 py-5 text-sm">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Label kecil</label>
                <input type="text" wire:model="form.eyebrow" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Judul</label>
                <input type="text" wire:model="form.heading" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Sub-judul</label>
                <textarea wire:model="form.subheading" rows="2" class="w-full rounded-lg border-slate-300 text-sm"></textarea>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Isi (boleh memakai tag HTML sederhana)</label>
                <textarea wire:model="form.content" rows="6" class="w-full rounded-lg border-slate-300 font-mono text-xs"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3 border-t border-slate-200 pt-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Ukuran judul (px)</label>
                    <input type="number" wire:model="form.heading_size" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Ukuran teks (px)</label>
                    <input type="number" wire:model="form.text_size" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Perataan</label>
                    <select wire:model="form.align" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">Bawaan</option>
                        <option value="left">Kiri</option>
                        <option value="center">Tengah</option>
                        <option value="right">Kanan</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Warna latar</label>
                    <input type="color" wire:model="form.background" class="h-9 w-full rounded-lg border-slate-300">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Jarak atas (px)</label>
                    <input type="number" wire:model="form.padding_top" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Jarak bawah (px)</label>
                    <input type="number" wire:model="form.padding_bottom" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
            </div>

            <div class="flex gap-2 border-t border-slate-200 pt-4">
                <button type="button" wire:click="saveEditor" class="btn btn-primary flex-1 justify-center">Simpan</button>
                <button type="button" wire:click="closeEditor" class="btn btn-outline">Batal</button>
            </div>
        </div>
    </div>

    {{-- Daftar jenis blok untuk tombol tambah --}}
    <template x-teleport="body">
        <div x-data="{ open: false, after: null }"
             x-on:open-add-block.window="open = true; after = $event.detail.after"
             x-show="open" x-cloak
             class="fixed inset-0 z-[80] flex items-center justify-center bg-black/40 p-4">
            <div @click.outside="open = false" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                <p class="mb-4 text-sm font-semibold text-slate-900">Tambah blok</p>
                <div class="grid grid-cols-3 gap-2">
                    @foreach($types as $key => $label)
                        <button type="button"
                                @click="$wire.add('{{ $key }}', after); open = false"
                                class="rounded-lg border border-slate-200 px-3 py-3 text-xs font-medium text-slate-700 hover:border-[var(--brand)] hover:text-[var(--brand)]">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </template>
</div>
