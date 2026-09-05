<?php

namespace App\Services;

use App\Models\ImportantDate;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class ConferenceDeadlines
{
    public const KINDS = [
        'abstract' => 'Abstract submission', 'revision' => 'Abstract revision',
        'acceptance' => 'Acceptance notification', 'payment' => 'Registration payment',
        'full_paper' => 'Full paper', 'conference' => 'Conference day',
    ];

    public function date(string $kind, ?int $editionId = null): ?CarbonInterface
    {
        $item = ImportantDate::where('edition_id', $editionId ?? currentEdition()?->id)
            ->where('kind', $kind)->first();

        return $item?->closes_at ?? $item?->date?->copy()->endOfDay();
    }

    public function isOpen(string $kind, ?int $editionId = null): bool
    {
        $deadline = $this->date($kind, $editionId);

        return ! $deadline || now()->lte($deadline);
    }

    public function assertOpen(string $kind, ?int $editionId = null, string $field = 'deadline'): void
    {
        if (! $this->isOpen($kind, $editionId)) {
            throw ValidationException::withMessages([$field => app()->getLocale() === 'id'
                ? 'Tenggat tahap ini telah berakhir. Hubungi panitia jika Anda memerlukan bantuan.'
                : 'The deadline for this stage has passed. Contact the committee if you need assistance.']);
        }
    }
}
