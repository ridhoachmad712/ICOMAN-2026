<x-filament-panels::page>
    @php
        $submission = $record->submission;
        $blind = $this->isBlind();
    @endphp

    <div class="grid items-start gap-6 xl:grid-cols-2">
        {{-- KIRI: naskah yang dinilai --}}
        <x-filament::section icon="heroicon-o-document-text" icon-color="primary">
            <x-slot name="heading">{{ __('review.manuscript') }}</x-slot>
            <x-slot name="description">
                {{ $submission?->submission_number }}
                @if($submission?->topic) · {{ $submission->topic->title }} @endif
            </x-slot>

            <p class="text-base font-semibold text-gray-950 dark:text-white">{{ $submission?->title }}</p>

            @if($blind)
                <p class="mt-2 text-xs italic text-gray-500">{{ __('review.authors_hidden') }}</p>
            @else
                <p class="mt-2 text-xs text-gray-500">
                    {{ $submission?->authors->sortBy('order')->pluck('name')->implode(', ') }}
                </p>
            @endif

            @if(filled($submission?->keywords))
                <p class="mt-3 text-xs text-gray-500"><strong>Keywords:</strong> {{ implode('; ', $submission->keywords) }}</p>
            @endif

            {{-- Area naskah bergulir sendiri supaya formulir di kanan tetap terjangkau. --}}
            <div class="mt-5 max-h-[32rem] overflow-y-auto rounded-xl border border-gray-200 p-5 dark:border-white/10">
                <x-extended-abstract-document :submission="$submission" />
            </div>

            <div class="mt-4">
                <x-filament::button
                    tag="a"
                    color="gray"
                    icon="heroicon-o-arrow-top-right-on-square"
                    href="{{ route('admin.submissions.extended-abstract.preview', $submission) }}"
                    target="_blank"
                >
                    {{ __('review.open_pdf') }}
                </x-filament::button>
            </div>
        </x-filament::section>

        {{-- KANAN: penilaian --}}
        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}

            <div class="flex items-center gap-3">
                <x-filament::button type="submit" icon="heroicon-o-check">
                    {{ $record->status === 'completed' ? __('review.continue') : __('review.open') }}
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    color="gray"
                    href="{{ \App\Filament\Resources\ReviewAssignments\ReviewAssignmentResource::getUrl('index') }}"
                >
                    {{ __('filament-panels::resources/pages/edit-record.form.actions.cancel.label') }}
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
