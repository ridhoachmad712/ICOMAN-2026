@props(['message' => null])

<div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
    <p class="text-slate-400">{{ $message ?? __('site.empty_section') }}</p>
</div>
