<?php

namespace App\Services;

use App\Filament\Resources\PresentationRooms\ModerationActions;
use App\Models\RoomPresentation;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/** Rekap absensi, nilai per kriteria, dan pemenang presentasi dalam Excel. */
class PresentationResultExport
{
    /** @return list<string> */
    public static function headings(): array
    {
        return [
            'No', 'Room', 'Moderator', 'Kode paper', 'Judul paper', 'Penulis', 'Afiliasi', 'Kehadiran', 'Presenter',
            ...array_values(RoomPresentation::PAPER_CRITERIA),
            'Nilai paper',
            ...array_values(RoomPresentation::PRESENTER_CRITERIA),
            'Nilai presenter',
            'Best Paper', 'Best Presenter', 'Catatan moderator',
        ];
    }

    public function create(Builder $presentations): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rekap-');

        if ($path === false) {
            throw new RuntimeException('Berkas sementara tidak dapat dibuat.');
        }

        $options = new Options;
        foreach ([6, 16, 22, 18, 60, 40, 34, 18, 26] as $index => $width) {
            $options->setColumnWidth($width, $index + 1);
        }

        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(self::headings(), (new Style)->setFontBold()));

        $number = 0;
        foreach ($presentations->clone()->with(['submission.authors', 'submission.author', 'room.moderator'])->get() as $item) {
            /** @var RoomPresentation $item */
            $score = fn (?array $scores, string $key) => isset($scores[$key]) ? (int) $scores[$key] : '';

            $writer->addRow(Row::fromValues([
                ++$number,
                (string) $item->room?->name,
                (string) $item->room?->moderator?->name,
                (string) $item->submission?->submission_number,
                (string) $item->submission?->title,
                implode(', ', ModerationActions::writers($item)),
                (string) $item->submission?->author?->affiliation,
                RoomPresentation::ATTENDANCE[$item->attendance] ?? 'Belum diabsen',
                (string) $item->presenter_name,
                ...array_map(fn (string $key) => $score($item->paper_scores, $key), array_keys(RoomPresentation::PAPER_CRITERIA)),
                $item->paper_score !== null ? (float) $item->paper_score : '',
                ...array_map(fn (string $key) => $score($item->presenter_scores, $key), array_keys(RoomPresentation::PRESENTER_CRITERIA)),
                $item->presenter_score !== null ? (float) $item->presenter_score : '',
                $item->best_paper_rank ? 'Juara '.$item->best_paper_rank : '',
                $item->best_presenter_rank ? 'Juara '.$item->best_presenter_rank : '',
                (string) $item->moderator_notes,
            ]));
        }

        $writer->close();

        return $path;
    }

    public function fileName(): string
    {
        return 'rekap-presentasi-'.now()->format('Ymd-His').'.xlsx';
    }
}
