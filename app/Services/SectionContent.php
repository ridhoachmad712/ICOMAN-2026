<?php

namespace App\Services;

use App\Models\Committee;
use App\Models\Download;
use App\Models\Faq;
use App\Models\Gallery;
use App\Models\ImportantDate;
use App\Models\News;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\RegistrationFee;
use App\Models\Schedule;
use App\Models\Speaker;
use App\Models\Sponsor;
use App\Models\Topic;
use Illuminate\Support\Collection;

/**
 * Data yang dibutuhkan tiap jenis blok halaman.
 *
 * Blok mengambil datanya sendiri lewat kelas ini, bukan dititipkan controller,
 * supaya blok yang sama bisa dipasang di halaman mana pun tanpa mengubah
 * controller halaman itu.
 */
class SectionContent
{
    /** Blok yang tidak punya isi apa pun tidak perlu dirender sama sekali. */
    public function isEmpty(PageSection $section): bool
    {
        return match ($section->type) {
            // Blok pembicara sengaja tidak ikut: tanpa data ia menampilkan
            // "Segera Diumumkan", yang memang ingin dilihat pengunjung.
            'topics', 'fees', 'important_dates', 'schedule',
            'committee', 'gallery', 'news', 'faq', 'sponsors', 'downloads' => $this->records($section)->isEmpty(),
            'page_content' => $this->page($section) === null,
            'rich_text' => blank($section->content),
            'image' => ! $section->hasMedia('section'),
            default => false,
        };
    }

    /** @return Collection<int, mixed> */
    public function records(PageSection $section): Collection
    {
        $editionId = currentEdition()?->id;
        $limit = (int) $section->setting('limit', 0);

        $query = match ($section->type) {
            'speakers' => Speaker::where('is_published', true)->with('media')->orderBy('order'),
            'topics' => Topic::query()->orderBy('order'),
            'fees' => RegistrationFee::query()->orderBy('order'),
            'important_dates' => ImportantDate::query()->orderBy('order'),
            'schedule' => Schedule::query()->orderBy('day_date')->orderBy('time_start'),
            'committee' => Committee::where('is_published', true)->with('media')->orderBy('order'),
            'gallery' => Gallery::with('media')->orderBy('order'),
            'news' => News::publiclyVisible()->where('is_published', true)->with('media')->orderByDesc('published_at'),
            'faq' => Faq::query()->orderBy('order'),
            'sponsors' => Sponsor::where('is_published', true)->with('media')->orderBy('order'),
            'downloads' => Download::query()->orderBy('order'),
            default => null,
        };

        if ($query === null) {
            return collect();
        }

        // FAQ boleh berlaku lintas edisi; sisanya selalu milik edisi berjalan.
        if ($section->type === 'faq') {
            $query->where(fn ($inner) => $inner->where('edition_id', $editionId)->orWhereNull('edition_id'));
        } elseif ($editionId !== null) {
            $query->where('edition_id', $editionId);
        }

        return $limit > 0 ? $query->limit($limit)->get() : $query->get();
    }

    /** Sponsor dikelompokkan per tingkatan, seperti tampilan aslinya. */
    public function sponsorsByTier(PageSection $section): Collection
    {
        return $this->records($section)->groupBy('tier');
    }

    public function page(PageSection $section): ?Page
    {
        $slug = $section->setting('page_slug');

        if (blank($slug)) {
            return null;
        }

        return Page::where('slug', $slug)
            ->where('is_published', true)
            ->when(currentEdition(), fn ($query, $edition) => $query
                ->where(fn ($inner) => $inner->whereNull('edition_id')->orWhere('edition_id', $edition->id)))
            ->first();
    }

    /** Tenggat terdekat yang belum lewat, untuk hitung mundur di hero. */
    public function nextDeadline(): ?ImportantDate
    {
        return ImportantDate::query()
            ->when(currentEdition(), fn ($query, $edition) => $query->where('edition_id', $edition->id))
            ->get()
            ->filter(fn (ImportantDate $date) => $date->date && $date->date->copy()->endOfDay()->isFuture())
            ->sortBy('date')
            ->first();
    }
}
