<div class="card p-6 sm:p-8">
    <form wire:submit="submit" class="space-y-6">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.paper_title') }} *</label>
            <input type="text" wire:model="title"
                   class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)]">
            @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.topic') }}</label>
            <select wire:model="topic_id" class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                <option value="">{{ __('author.select_topic') }}</option>
                @foreach($topics as $topic)
                    <option value="{{ $topic->id }}">{{ $topic->title }}</option>
                @endforeach
            </select>
            @error('topic_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.abstract_en') }} *</label>
            <textarea wire:model="abstract" rows="5" class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)]"></textarea>
            @error('abstract')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.abstract_id') }} <span class="text-slate-400">({{ __('author.optional') }})</span></label>
            <textarea wire:model="abstract_id" rows="4" class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)]"></textarea>
            @error('abstract_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.paper_file') }} * <span class="text-slate-400">({{ __('author.file_hint') }})</span></label>
            <input type="file" wire:model="paper" accept=".pdf,.doc,.docx"
                   class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-[var(--brand)]/10 file:px-4 file:py-2 file:text-[var(--brand)] file:font-semibold">
            <div wire:loading wire:target="paper" class="mt-1 text-xs text-slate-400">{{ __('author.uploading') }}</div>
            @error('paper')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        {{-- Co-authors repeater --}}
        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="block text-sm font-medium text-slate-700">{{ __('author.authors') }} *</label>
                <button type="button" wire:click="addAuthor" class="text-sm font-medium text-[var(--brand)] hover:underline">+ {{ __('author.add_author') }}</button>
            </div>

            <div class="space-y-3">
                @foreach($authors as $i => $a)
                    <div wire:key="author-{{ $i }}" class="rounded-lg border border-slate-200 p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold text-slate-500">
                                {{ __('author.author_num', ['num' => $i + 1]) }}
                                @if($a['is_corresponding'] ?? false)<span class="text-[var(--brand)]">· {{ __('author.corresponding') }}</span>@endif
                            </span>
                            @if($i > 0)
                                <button type="button" wire:click="removeAuthor({{ $i }})" class="text-xs text-red-500 hover:underline">{{ __('author.remove') }}</button>
                            @endif
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <input type="text" wire:model="authors.{{ $i }}.name" placeholder="{{ __('author.name') }}"
                                       class="w-full rounded-lg border-slate-300 text-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                                @error("authors.$i.name")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <input type="email" wire:model="authors.{{ $i }}.email" placeholder="{{ __('author.email') }}"
                                       class="w-full rounded-lg border-slate-300 text-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                                @error("authors.$i.email")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <input type="text" wire:model="authors.{{ $i }}.affiliation" placeholder="{{ __('author.affiliation') }}"
                                       class="w-full rounded-lg border-slate-300 text-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="pt-2">
            <button type="submit" wire:loading.attr="disabled" wire:target="submit,paper" class="btn btn-primary disabled:opacity-50">
                <span wire:loading.remove wire:target="submit">{{ __('author.send_paper') }}</span>
                <span wire:loading wire:target="submit">{{ __('author.sending') }}</span>
            </button>
        </div>
    </form>
</div>
