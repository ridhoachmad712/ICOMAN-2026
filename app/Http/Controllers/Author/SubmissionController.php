<?php

namespace App\Http\Controllers\Author;

use App\Filament\Author\Resources\Papers\PaperResource;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Services\AbstractWordDocument;
use App\Services\ConferenceDeadlines;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SubmissionController extends Controller
{
    public function create(): RedirectResponse
    {
        $author = Auth::guard('author')->user();
        abort_unless($author->isPresenter(), 403, 'Submission abstrak hanya tersedia untuk akun presenter.');

        $existing = $author->submissions()
            ->when(currentEdition(), fn ($query, $edition) => $query->where('edition_id', $edition->id))
            ->latest('submitted_at')
            ->first();

        if ($existing) {
            return redirect()->to(PaperResource::getUrl('view', ['record' => $existing], panel: 'author'))
                ->with('status', app()->getLocale() === 'id'
                    ? 'Setiap akun hanya dapat mengirim satu abstrak pada edisi konferensi ini.'
                    : 'Each account may submit only one abstract in this conference edition.');
        }

        return redirect()->to(PaperResource::getUrl('create', panel: 'author'));
    }

    public function show(Submission $submission): RedirectResponse
    {
        $this->authorizeOwner($submission);

        return redirect()->to(PaperResource::getUrl('view', ['record' => $submission], panel: 'author'));
    }

    public function loa(Submission $submission): Response
    {
        $this->authorizeOwner($submission);
        abort_unless($submission->status === 'accepted' && $submission->isLoaIssued(), 404);

        $submission->load(['authors' => fn ($query) => $query->orderBy('order'), 'edition']);

        // LOA di-generate otomatis sebagai PDF resmi.
        return Pdf::loadView('pdf.loa', compact('submission'))
            ->setPaper('a4')
            ->stream($submission->submission_number.'-LOA.pdf');
    }

    /** Presenter mengunggah naskah lengkap (full paper) setelah pembayaran terverifikasi. */
    public function submitFullPaper(Request $request, Submission $submission): RedirectResponse
    {
        $this->authorizeOwner($submission);

        abort_unless($submission->canSubmitFullPaper(), 403, app()->getLocale() === 'id'
            ? 'Full paper hanya dapat dikirim setelah LOA terbit dan pembayaran registrasi terverifikasi.'
            : 'The full paper can only be submitted after the LOA is issued and the registration payment is verified.');

        $request->validate([
            'full_paper' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
        ]);

        app(ConferenceDeadlines::class)->assertOpen('full_paper', $submission->edition_id, 'full_paper');
        // Retain previous private versions. A failed upload leaves the old file intact.
        $submission->addMediaFromRequest('full_paper')->toMediaCollection('camera_ready');
        $submission->forceFill(['full_paper_submitted_at' => now()])->save();

        return back()->with('status', app()->getLocale() === 'id'
            ? 'Naskah lengkap (full paper) berhasil dikirim.'
            : 'Your full paper has been submitted successfully.');
    }

    /** Unduh full paper milik sendiri. */
    public function downloadFullPaper(Submission $submission): Response
    {
        $this->authorizeOwner($submission);

        return $this->fullPaperDownload($submission);
    }

    public function previewExtendedAbstract(Submission $submission): Response
    {
        $this->authorizeOwner($submission);

        return $this->extendedAbstractPdf($submission);
    }

    public function downloadFullPaperForAdmin(Submission $submission): Response
    {
        $user = Auth::guard('web')->user();
        abort_unless($user && ($user->hasAnyRole(['superadmin', 'admin_registrasi'])
            || $submission->reviewAssignments()->where('reviewer_id', $user->id)->exists()), 403);
        return $this->fullPaperDownload($submission);
    }

    /**
     * Unduhan full paper untuk author maupun panitia.
     *
     * Header dikirim lewat argumen download(), bukan ->header(): yang
     * dikembalikan adalah BinaryFileResponse milik Symfony, yang tidak punya
     * method itu — pemanggilan berantai dulu membuat setiap unduhan admin
     * berakhir 500. Berkas yang tercatat tapi hilang dari disk dilaporkan ke
     * log dan dijawab 404 yang jelas, bukan 500.
     */
    private function fullPaperDownload(Submission $submission): Response
    {
        $media = $submission->fullPaperMedia();
        abort_unless($media, 404);

        $path = $media->getPath();

        if (! is_file($path) || ! is_readable($path)) {
            Log::error('Berkas full paper tercatat tetapi tidak dapat dibaca dari disk.', [
                'submission_id' => $submission->id,
                'submission_number' => $submission->submission_number,
                'media_id' => $media->id,
                'disk' => $media->disk,
                'path' => $path,
                'exists' => file_exists($path),
            ]);

            abort(404, app()->getLocale() === 'id'
                ? 'Berkas full paper tidak ditemukan di server. Panitia sudah dapat melihat catatannya; minta author mengunggah ulang bila perlu.'
                : 'The full paper file could not be found on the server. Please ask the author to upload it again if needed.');
        }

        return response()->download($path, $media->file_name, ['Cache-Control' => 'private, no-store']);
    }

    public function previewExtendedAbstractForAdmin(Submission $submission): Response
    {
        $user = Auth::guard('web')->user();
        abort_unless($user, 403);

        $mayReview = $submission->reviewAssignments()->where('reviewer_id', $user->id)->exists();
        $mayManage = $user->hasAnyRole(['superadmin', 'admin_registrasi']);
        abort_unless($mayReview || $mayManage, 403);

        // Reviewer murni menerima versi tanpa identitas penulis.
        return $this->extendedAbstractPdf($submission, blind: ! $mayManage);
    }

    /** Abstrak yang diterima dalam format Word, khusus panitia pengelola submission. */
    public function downloadAbstractWordForAdmin(Submission $submission, AbstractWordDocument $document): Response
    {
        abort_unless(Auth::guard('web')->user()?->managesSubmissions(), 403);
        abort_unless($submission->status === 'accepted', 404);

        return response()
            ->download($document->create($submission), $document->fileName($submission), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Cache-Control' => 'private, no-store',
            ])
            ->deleteFileAfterSend();
    }

    public function submitExtendedAbstract(Request $request, Submission $submission): RedirectResponse
    {
        $this->authorizeOwner($submission);
        abort_unless(in_array($submission->status, Submission::AUTHOR_EDITABLE_STATUSES, true), 403, 'Abstract tidak dapat diubah setelah dikirim ke reviewer.');

        // Endpoint formulir teks lama tidak boleh melewati pemeriksaan lima
        // bagian pada editor baru. Arahkan kembali tanpa mengubah submission.
        return redirect(PaperResource::getUrl('extended-abstract', ['record' => $submission], panel: 'author'))
            ->with('status', app()->getLocale() === 'id'
                ? 'Lengkapi dan kirim abstract melalui formulir di portal.'
                : 'Complete and submit your abstract through the portal form.');
    }

    private function authorizeOwner(Submission $submission): void
    {
        abort_unless($submission->author_id === Auth::guard('author')->id(), 403);
    }

    /**
     * Render PDF abstrak. Kegagalan dompdf (mis. batas memori atau direktori
     * cache font tidak dapat ditulis di server) dulu tampil sebagai layar 500
     * kosong sehingga tidak bisa didiagnosis; sekarang dicatat ke log dengan
     * konteks, dan pembaca mendapat pesan yang jelas.
     */
    private function extendedAbstractPdf(Submission $submission, bool $blind = false): Response
    {
        $submission->loadMissing(['edition', 'topic', 'authors']);

        try {
            return Pdf::loadView('pdf.extended-abstract', compact('submission', 'blind'))
                ->setPaper('a4')
                ->stream($submission->submission_number.'-extended-abstract.pdf');
        } catch (\Throwable $e) {
            report($e);
            Log::error('Gagal membuat PDF abstrak.', [
                'submission_id' => $submission->id,
                'submission_number' => $submission->submission_number,
                'word_count' => $submission->abstractWordCount(),
                'memory_limit' => ini_get('memory_limit'),
                'font_dir_writable' => is_writable(storage_path()),
                'error' => $e->getMessage(),
            ]);

            $isId = app()->getLocale() === 'id';

            return response(
                $isId
                    ? 'PDF abstrak gagal dibuat. Kesalahan sudah dicatat pada log sistem; silakan hubungi admin dan sebutkan nomor '.$submission->submission_number.'.'
                    : 'The abstract PDF could not be generated. The error has been written to the system log; please contact the administrator quoting '.$submission->submission_number.'.',
                503,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }
    }
}
