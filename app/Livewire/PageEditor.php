<?php

namespace App\Livewire;

use App\Models\PageSection;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Penyuntingan langsung di atas halaman publik.
 *
 * Penyunting membuka halaman biasa dengan ?edit=1, lalu memindahkan,
 * menyembunyikan, menggandakan, menghapus, dan menambah blok tanpa berpindah ke
 * panel admin. Teks judul disunting langsung pada tampilannya.
 *
 * Kewenangan diperiksa ulang di setiap aksi, bukan hanya saat halaman dirender:
 * komponen Livewire menerima permintaan dari peramban, jadi gerbang di tampilan
 * saja tidak cukup.
 */
class PageEditor extends Component
{
    public string $target;

    /** Blok yang sedang dibuka panel pengaturannya, bila ada. */
    public ?int $editing = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** Isian teks yang boleh disunting langsung di halaman. */
    private const TEXT_FIELDS = ['eyebrow', 'heading', 'subheading'];

    /** Jenis blok yang masuk akal ditambahkan dari halaman. */
    public const QUICK_TYPES = [
        'heading' => 'Judul',
        'rich_text' => 'Teks bebas',
        'image' => 'Gambar',
        'buttons' => 'Tombol',
        'columns' => 'Kolom',
        'cards' => 'Kartu',
        'stats' => 'Angka',
        'quote' => 'Kutipan',
        'accordion' => 'Akordeon',
        'video' => 'Video',
        'spacer' => 'Ruang kosong',
        'divider' => 'Garis',
    ];

    public function mount(string $target): void
    {
        abort_unless(canEditPages(), 403);

        $this->target = $target;
    }

    /**
     * Blok halaman ini sebagai baris tersimpan. Selama halaman masih memakai
     * susunan bawaan, susunannya disalin dulu — kalau tidak, tidak ada yang
     * bisa dipindahkan atau dihapus.
     */
    private function sections(): Collection
    {
        if (PageSection::where('target', $this->target)->doesntExist()) {
            PageSection::installDefaults($this->target);
        }

        return PageSection::where('target', $this->target)->orderBy('order')->get();
    }

    private function guard(): void
    {
        abort_unless(canEditPages(), 403);
    }

    private function find(int $id): PageSection
    {
        return PageSection::where('target', $this->target)->findOrFail($id);
    }

    // --- Menyusun ulang -----------------------------------------------------

    public function move(int $id, int $direction): void
    {
        $this->guard();

        $sections = $this->sections()->values();
        $index = $sections->search(fn (PageSection $section) => $section->id === $id);

        if ($index === false) {
            return;
        }

        $swapWith = $index + ($direction < 0 ? -1 : 1);

        if ($swapWith < 0 || $swapWith >= $sections->count()) {
            return;
        }

        // Urutan ditulis ulang seluruhnya supaya tidak ada nomor kembar yang
        // membuat urutannya bergantung pada kebetulan.
        $reordered = $sections->all();
        [$reordered[$index], $reordered[$swapWith]] = [$reordered[$swapWith], $reordered[$index]];

        foreach ($reordered as $position => $section) {
            $section->update(['order' => $position]);
        }

        $this->refreshPage();
    }

    public function toggleVisibility(int $id): void
    {
        $this->guard();

        $section = $this->find($id);
        $section->update(['is_published' => ! $section->is_published]);

        $this->refreshPage();
    }

    public function duplicate(int $id): void
    {
        $this->guard();

        $section = $this->find($id);
        $copy = $section->replicate();
        $copy->order = $section->order + 1;
        $copy->save();

        // Beri ruang pada blok sesudahnya agar urutannya tetap jelas.
        PageSection::where('target', $this->target)
            ->where('id', '!=', $copy->id)
            ->where('order', '>=', $copy->order)
            ->increment('order');

        $this->refreshPage();
    }

    public function remove(int $id): void
    {
        $this->guard();

        $this->find($id)->delete();

        $this->refreshPage();
    }

    public function add(string $type, ?int $afterId = null): void
    {
        $this->guard();

        abort_unless(array_key_exists($type, PageSection::TYPES), 422);

        $after = $afterId ? $this->find($afterId) : null;
        $order = $after ? $after->order + 1 : ($this->sections()->max('order') + 1);

        PageSection::where('target', $this->target)->where('order', '>=', $order)->increment('order');

        $section = PageSection::create([
            'target' => $this->target,
            'type' => $type,
            'order' => $order,
            'is_published' => true,
        ]);

        $this->editing = $section->id;
        $this->loadForm();

        $this->refreshPage();
    }

    // --- Menyunting teks langsung di halaman --------------------------------

    public function updateText(int $id, string $field, string $value): void
    {
        $this->guard();

        abort_unless(in_array($field, self::TEXT_FIELDS, true), 422);

        $section = $this->find($id);
        // Hanya bahasa yang sedang ditampilkan yang diubah; terjemahan lain tetap.
        $section->setTranslation($field, app()->getLocale(), trim(strip_tags($value)))->save();

        $this->refreshPage();
    }

    // --- Panel pengaturan ---------------------------------------------------

    public function edit(int $id): void
    {
        $this->guard();

        $this->editing = $id;
        $this->loadForm();
    }

    public function closeEditor(): void
    {
        $this->editing = null;
        $this->form = [];
    }

    private function loadForm(): void
    {
        $section = $this->find($this->editing);
        $locale = app()->getLocale();

        $this->form = [
            'eyebrow' => $section->getTranslation('eyebrow', $locale, false),
            'heading' => $section->getTranslation('heading', $locale, false),
            'subheading' => $section->getTranslation('subheading', $locale, false),
            'content' => $section->getTranslation('content', $locale, false),
            'heading_size' => $section->setting('appearance.heading_size'),
            'text_size' => $section->setting('appearance.text_size'),
            'align' => $section->setting('appearance.align'),
            'background' => $section->setting('appearance.background'),
            'padding_top' => $section->setting('appearance.padding_top'),
            'padding_bottom' => $section->setting('appearance.padding_bottom'),
        ];
    }

    public function saveEditor(): void
    {
        $this->guard();

        $section = $this->find($this->editing);
        $locale = app()->getLocale();

        foreach (['eyebrow', 'heading', 'subheading', 'content'] as $field) {
            $section->setTranslation($field, $locale, (string) ($this->form[$field] ?? ''));
        }

        // Setelan tampilan lain pada blok ini tidak boleh ikut terhapus.
        $settings = $section->settings ?? [];
        $appearance = $settings['appearance'] ?? [];

        foreach (['heading_size', 'text_size', 'align', 'background', 'padding_top', 'padding_bottom'] as $key) {
            $value = $this->form[$key] ?? null;
            if (blank($value)) {
                unset($appearance[$key]);
            } else {
                $appearance[$key] = $value;
            }
        }

        $settings['appearance'] = $appearance;
        $section->settings = $settings;
        $section->save();

        $this->closeEditor();
        $this->refreshPage();
    }

    /** Halaman dimuat ulang agar hasilnya terlihat apa adanya, bukan tiruan. */
    private function refreshPage(): void
    {
        $this->dispatch('page-section-changed');
    }

    public function render()
    {
        return view('livewire.page-editor', [
            'types' => self::QUICK_TYPES,
        ]);
    }
}
