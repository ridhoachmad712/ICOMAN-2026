<?php

namespace App\Livewire\Author;

use App\Models\Submission;
use App\Models\SubmissionAuthor;
use App\Models\Topic;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class SubmitPaper extends Component
{
    use WithFileUploads;

    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('nullable|integer|exists:topics,id')]
    public ?int $topic_id = null;

    #[Validate('required|string|max:5000')]
    public string $abstract = '';

    #[Validate('nullable|string|max:5000')]
    public string $abstract_id = '';

    #[Validate('required|file|mimes:pdf,doc,docx|max:20480')]
    public $paper;

    /** Daftar author (co-author). Entri pertama = corresponding (submitter). */
    public array $authors = [];

    public function mount(): void
    {
        $me = Auth::guard('author')->user();

        $this->authors = [[
            'name' => $me->name,
            'email' => $me->email,
            'affiliation' => $me->affiliation ?? '',
            'is_corresponding' => true,
        ]];
    }

    public function addAuthor(): void
    {
        $this->authors[] = ['name' => '', 'email' => '', 'affiliation' => '', 'is_corresponding' => false];
    }

    public function removeAuthor(int $index): void
    {
        if ($index === 0) {
            return; // corresponding author tidak bisa dihapus
        }

        unset($this->authors[$index]);
        $this->authors = array_values($this->authors);
    }

    protected function rules(): array
    {
        return [
            'authors' => ['required', 'array', 'min:1'],
            'authors.*.name' => ['required', 'string', 'max:255'],
            'authors.*.email' => ['required', 'email', 'max:255'],
            'authors.*.affiliation' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function submit()
    {
        $this->validate();

        $me = Auth::guard('author')->user();

        $submission = Submission::create([
            'edition_id' => currentEdition()?->id,
            'author_id' => $me->id,
            'topic_id' => $this->topic_id,
            'title' => $this->title,
            'abstract' => $this->abstract,
            'abstract_id' => $this->abstract_id ?: null,
        ]);

        $submission->addMedia($this->paper->getRealPath())
            ->usingFileName($this->paper->getClientOriginalName())
            ->toMediaCollection('paper');

        foreach (array_values($this->authors) as $i => $a) {
            SubmissionAuthor::create([
                'submission_id' => $submission->id,
                'name' => $a['name'],
                'email' => $a['email'],
                'affiliation' => $a['affiliation'] ?: null,
                'is_corresponding' => (bool) ($a['is_corresponding'] ?? false),
                'order' => $i,
            ]);
        }

        $me->notify(new \App\Notifications\SubmissionReceived($submission));

        session()->flash('status', __('Paper berhasil dikirim. Nomor: ').$submission->submission_number);

        return redirect()->route('author.dashboard');
    }

    public function render()
    {
        return view('livewire.author.submit-paper', [
            'topics' => Topic::where('edition_id', currentEdition()?->id)->orderBy('order')->get(),
        ]);
    }
}
