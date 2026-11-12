<x-filament-panels::page>
    {{-- Gaya sebaris: panel admin memakai CSS bawaan Filament tanpa build Tailwind sendiri. --}}
    @php
        $types = \App\Models\Certificate::TYPES;
        $muted = 'color:var(--gray-500);';
        $field = 'display:flex;flex-direction:column;gap:.25rem;font-size:.8125rem;';
        $control = 'width:100%;padding:.375rem .5rem;border-radius:.5rem;border:1px solid rgba(128,128,128,.4);background:transparent;color:inherit;font-size:.875rem;';
    @endphp

    <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
        @foreach($types as $key => $label)
            <x-filament::button tag="a" size="sm" :color="$type === $key ? 'primary' : 'gray'"
                                href="{{ \App\Filament\Pages\Settings\CertificateDesigner::getUrl(['type' => $key]) }}">
                {{ $key === 'presentation' ? 'Presenter' : 'Peserta seminar' }}
            </x-filament::button>
        @endforeach
    </div>

    @unless($customLayout)
        <x-filament::section>
            <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem;font-size:.875rem;">
                <span>Template custom belum aktif, jadi sertifikat masih memakai susunan bawaan dan posisi di sini belum dipakai.</span>
                <x-filament::button size="sm" wire:click="enableCustomLayout">Aktifkan template custom</x-filament::button>
            </div>
        </x-filament::section>
    @endunless

    @if($config['font'])
        <style>
            @font-face { font-family: '{{ $config['font']['family'] }}'; src: url('{{ $config['font']['url'] }}') format('truetype'); }
        </style>
    @endif

    <div wire:ignore x-data="certificateDesigner(@js($config))" x-on:keydown.window="nudge($event)"
         style="display:flex;flex-wrap:wrap;gap:1.5rem;align-items:flex-start;">

        {{-- Kanvas A4 landscape (297 × 210 mm) --}}
        <div style="flex:1 1 36rem;min-width:0;">
            <div x-ref="canvas" x-on:pointerdown.self="selected = null"
                 style="position:relative;width:100%;aspect-ratio:297/210;background:#fff;border-radius:.5rem;overflow:hidden;box-shadow:0 0 0 1px rgba(128,128,128,.35);touch-action:none;"
                 {{-- Objek, bukan string: string akan menimpa gaya kanvas di atas. --}}
                 :style="background ? { backgroundImage: `url('${background}')`, backgroundSize: '100% 100%' } : {}">
                <template x-if="! background">
                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:.875rem;text-align:center;padding:1rem;pointer-events:none;">
                        Belum ada gambar latar. Unggah lewat tombol "Gambar latar &amp; huruf".
                    </div>
                </template>

                <template x-for="key in Object.keys(labels)" :key="key">
                    <div :style="boxStyle(key)" x-on:pointerdown.stop.prevent="startDrag($event, key, 'move')" :title="labels[key]">
                        <template x-if="key === 'qr'">
                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:repeating-conic-gradient(#111 0% 25%, #fff 0% 50%) 50% / 20% 20%;color:#fff;font:600 11px sans-serif;">
                                <span style="background:#111;padding:1px 4px;border-radius:3px;">QR</span>
                            </div>
                        </template>
                        <template x-if="key !== 'qr'">
                            <span x-text="text(key)"></span>
                        </template>
                        {{-- Pegangan kanan: lebar kotak teks, atau ukuran QR. --}}
                        <div x-show="selected === key" x-on:pointerdown.stop.prevent="startDrag($event, key, 'resize')"
                             title="Tarik untuk mengubah ukuran"
                             style="position:absolute;right:-6px;bottom:-6px;width:12px;height:12px;border-radius:3px;background:#f59e0b;border:2px solid #fff;cursor:nwse-resize;"></div>
                    </div>
                </template>
            </div>

            <p style="margin:.5rem 0 0;font-size:.8125rem;{{ $muted }}">
                Klik elemen untuk memilih, geser untuk memindahkan, tarik kotak oranye untuk mengubah lebar (atau ukuran QR).
                Tombol panah menggeser 0,5 mm; tahan Shift untuk 5 mm.
            </p>
        </div>

        {{-- Panel gaya --}}
        <div style="flex:0 1 20rem;min-width:16rem;display:flex;flex-direction:column;gap:1rem;">
            <x-filament::section>
                <x-slot name="heading">Elemen</x-slot>
                <div style="display:flex;flex-direction:column;gap:.375rem;">
                    <template x-for="key in Object.keys(labels)" :key="key">
                        <div style="display:flex;align-items:center;gap:.5rem;font-size:.875rem;">
                            <input type="checkbox" class="fi-checkbox-input" :disabled="key === 'name'"
                                   :checked="layout[key].visible !== false" x-on:change="layout[key].visible = $event.target.checked; dirty = true"
                                   :title="key === 'name' ? 'Nama selalu dicetak' : 'Tampilkan'">
                            <button type="button" x-on:click="selected = key" x-text="labels[key]"
                                    :style="`background:none;border:0;padding:0;cursor:pointer;color:inherit;text-align:left;font-weight:${selected === key ? 700 : 400};text-decoration:${layout[key].visible === false ? 'line-through' : 'none'};`"></button>
                        </div>
                    </template>
                </div>
            </x-filament::section>

            <template x-if="selected">
                <x-filament::section>
                    <x-slot name="heading"><span x-text="labels[selected]"></span></x-slot>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                        <label style="{{ $field }}">Dari atas (mm)
                            <input type="number" step="0.5" min="0" max="210" style="{{ $control }}" x-model.number="layout[selected].top" x-on:input="dirty = true">
                        </label>
                        <label style="{{ $field }}">Dari kiri (mm)
                            <input type="number" step="0.5" min="0" max="297" style="{{ $control }}" x-model.number="layout[selected].left" x-on:input="dirty = true">
                        </label>

                        <template x-if="selected === 'qr'">
                            <label style="{{ $field }}">Ukuran (mm)
                                <input type="number" step="0.5" min="10" max="80" style="{{ $control }}" x-model.number="layout.qr.size" x-on:input="dirty = true">
                            </label>
                        </template>

                        <template x-if="selected !== 'qr'">
                            <label style="{{ $field }}">Lebar kotak (mm)
                                <input type="number" step="0.5" min="10" max="297" style="{{ $control }}" x-model.number="layout[selected].width" x-on:input="dirty = true">
                            </label>
                        </template>
                        <template x-if="selected !== 'qr'">
                            <label style="{{ $field }}">Ukuran huruf (pt)
                                <input type="number" step="0.5" min="6" max="72" style="{{ $control }}" x-model.number="layout[selected].size" x-on:input="dirty = true">
                            </label>
                        </template>
                        <template x-if="selected !== 'qr'">
                            <label style="{{ $field }}grid-column:1 / -1;">Jenis huruf
                                <select style="{{ $control }}" x-model="layout[selected].font" x-on:change="dirty = true">
                                    <template x-for="(label, value) in fonts" :key="value">
                                        {{-- :selected, karena pilihan dibuat setelah x-model membaca nilainya. --}}
                                        <option :value="value" x-text="label" :selected="value === layout[selected].font" :disabled="value === 'custom' && ! font" style="color:#111;"></option>
                                    </template>
                                </select>
                            </label>
                        </template>
                        <template x-if="selected !== 'qr'">
                            <label style="{{ $field }}">Warna
                                <input type="color" style="{{ $control }}height:2.25rem;padding:.125rem;" x-model="layout[selected].color" x-on:input="dirty = true">
                            </label>
                        </template>
                        <template x-if="selected !== 'qr'">
                            <label style="{{ $field }}">Perataan
                                <select style="{{ $control }}" x-model="layout[selected].align" x-on:change="dirty = true">
                                    <option value="left" style="color:#111;">Kiri</option>
                                    <option value="center" style="color:#111;">Tengah</option>
                                    <option value="right" style="color:#111;">Kanan</option>
                                </select>
                            </label>
                        </template>
                        <template x-if="selected !== 'qr'">
                            <div style="grid-column:1 / -1;display:flex;flex-wrap:wrap;gap:.5rem 1rem;font-size:.875rem;">
                                <label style="display:flex;align-items:center;gap:.375rem;"><input type="checkbox" class="fi-checkbox-input" x-model="layout[selected].bold" x-on:change="dirty = true"> Tebal</label>
                                <label style="display:flex;align-items:center;gap:.375rem;"><input type="checkbox" class="fi-checkbox-input" x-model="layout[selected].italic" x-on:change="dirty = true"> Miring</label>
                                <label style="display:flex;align-items:center;gap:.375rem;"><input type="checkbox" class="fi-checkbox-input" x-model="layout[selected].uppercase" x-on:change="dirty = true"> Kapital semua</label>
                                <template x-if="selected === 'paper_title'">
                                    <label style="display:flex;align-items:center;gap:.375rem;"><input type="checkbox" class="fi-checkbox-input" x-model="layout.paper_title.quotes" x-on:change="dirty = true"> Tanda kutip</label>
                                </template>
                            </div>
                        </template>
                        <template x-if="selected === 'number'">
                            <label style="{{ $field }}grid-column:1 / -1;">Teks sebelum nomor (isi - untuk tanpa teks)
                                <input type="text" maxlength="30" style="{{ $control }}" x-model="layout.number.prefix" x-on:input="dirty = true">
                            </label>
                        </template>

                        <div style="grid-column:1 / -1;">
                            <x-filament::button size="sm" color="gray" x-on:click="centerSelected()">Tengahkan di kertas</x-filament::button>
                        </div>
                    </div>
                </x-filament::section>
            </template>

            <x-filament::section>
                <x-slot name="heading">Data contoh</x-slot>
                <label style="{{ $field }}">Nama (coba nama terpanjang peserta)
                    <input type="text" style="{{ $control }}" x-model="sample.name">
                </label>
            </x-filament::section>

            <div style="display:flex;align-items:center;gap:.75rem;">
                <x-filament::button x-on:click="save()" x-bind:disabled="saving">
                    <span x-text="saving ? 'Menyimpan…' : 'Simpan tata letak'"></span>
                </x-filament::button>
                <span x-show="dirty" style="font-size:.8125rem;color:var(--warning-600);">Ada perubahan belum disimpan</span>
            </div>
        </div>
    </div>

    @script
    <script>
        Alpine.data('certificateDesigner', (config) => ({
            layout: config.layout,
            labels: config.labels,
            fonts: config.fonts,
            font: config.font,
            background: config.background,
            sample: config.sample,
            selected: 'name',
            scale: 1,
            drag: null,
            dirty: false,
            saving: false,

            init() {
                for (const key of Object.keys(this.layout)) {
                    for (const flag of ['bold', 'italic', 'uppercase', 'visible', 'quotes']) {
                        if (flag in this.layout[key]) this.layout[key][flag] = [true, 1, '1', 'true'].includes(this.layout[key][flag]);
                    }
                }
                const observer = new ResizeObserver(() => this.measure());
                observer.observe(this.$refs.canvas);
                this.measure();
                window.addEventListener('pointermove', (event) => this.moveDrag(event));
                window.addEventListener('pointerup', () => (this.drag = null));
                window.addEventListener('beforeunload', (event) => { if (this.dirty) event.preventDefault(); });
            },

            measure() {
                this.scale = this.$refs.canvas.clientWidth / 297;
            },

            px(mm) {
                return `${(Number(mm) || 0) * this.scale}px`;
            },

            family(font) {
                return {
                    serif: "'DejaVu Serif', Georgia, serif",
                    mono: "'DejaVu Sans Mono', Consolas, monospace",
                    custom: this.font ? `'${this.font.family}', 'DejaVu Sans', sans-serif` : "'DejaVu Sans', Verdana, sans-serif",
                }[font] ?? "'DejaVu Sans', Verdana, sans-serif";
            },

            boxStyle(key) {
                const item = this.layout[key];
                let style = `position:absolute;top:${this.px(item.top)};left:${this.px(item.left)};cursor:move;user-select:none;`;
                style += `outline:${this.selected === key ? '2px solid #f59e0b' : '1px dashed rgba(37,99,235,.7)'};outline-offset:1px;`;
                style += `opacity:${item.visible === false ? 0.3 : 1};`;

                if (key === 'qr') {
                    return style + `width:${this.px(item.size)};height:${this.px(item.size)};`;
                }

                return style + `width:${this.px(item.width)};font-size:${(Number(item.size) || 12) * 0.3528 * this.scale}px;line-height:1.25;`
                    + `text-align:${item.align};color:${item.color};font-family:${this.family(item.font)};`
                    + `font-weight:${item.bold ? 'bold' : 'normal'};font-style:${item.italic ? 'italic' : 'normal'};`
                    + `text-transform:${item.uppercase ? 'uppercase' : 'none'};`;
            },

            text(key) {
                const item = this.layout[key];
                if (key === 'paper_title') return item.quotes ? `“${this.sample.paper_title}”` : this.sample.paper_title;
                if (key === 'number') {
                    const prefix = (item.prefix ?? 'No.').trim();
                    return (prefix && prefix !== '-' ? prefix + ' ' : '') + this.sample.number;
                }
                return this.sample[key] ?? '';
            },

            round(value) {
                return Math.round(value * 2) / 2;
            },

            clamp(value, min, max) {
                return Math.min(max, Math.max(min, value));
            },

            startDrag(event, key, mode) {
                this.selected = key;
                const item = this.layout[key];
                this.drag = { key, mode, x: event.clientX, y: event.clientY, top: +item.top, left: +item.left, width: +(item.width ?? 0), size: +(item.size ?? 0) };
            },

            moveDrag(event) {
                if (! this.drag) return;
                const item = this.layout[this.drag.key];
                const dx = (event.clientX - this.drag.x) / this.scale;
                const dy = (event.clientY - this.drag.y) / this.scale;

                if (this.drag.mode === 'move') {
                    item.left = this.clamp(this.round(this.drag.left + dx), 0, 297);
                    item.top = this.clamp(this.round(this.drag.top + dy), 0, 210);
                } else if (this.drag.key === 'qr') {
                    item.size = this.clamp(this.round(this.drag.size + Math.max(dx, dy)), 10, 80);
                } else {
                    item.width = this.clamp(this.round(this.drag.width + dx), 10, 297);
                }
                this.dirty = true;
            },

            nudge(event) {
                if (! this.selected || ! event.key.startsWith('Arrow') || ['INPUT', 'SELECT', 'TEXTAREA'].includes(event.target.tagName)) return;
                event.preventDefault();
                const step = event.shiftKey ? 5 : 0.5;
                const item = this.layout[this.selected];
                if (event.key === 'ArrowLeft') item.left = this.clamp(this.round(item.left - step), 0, 297);
                if (event.key === 'ArrowRight') item.left = this.clamp(this.round(item.left + step), 0, 297);
                if (event.key === 'ArrowUp') item.top = this.clamp(this.round(item.top - step), 0, 210);
                if (event.key === 'ArrowDown') item.top = this.clamp(this.round(item.top + step), 0, 210);
                this.dirty = true;
            },

            centerSelected() {
                const item = this.layout[this.selected];
                const width = this.selected === 'qr' ? item.size : item.width;
                item.left = this.round((297 - width) / 2);
                this.dirty = true;
            },

            async save() {
                this.saving = true;
                try {
                    await this.$wire.saveLayout(JSON.parse(JSON.stringify(this.layout)));
                    this.dirty = false;
                } finally {
                    this.saving = false;
                }
            },
        }));
    </script>
    @endscript
</x-filament-panels::page>
