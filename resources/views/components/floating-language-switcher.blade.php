@php
    $currentLocale = app()->getLocale();
@endphp

<aside aria-label="{{ $currentLocale === 'id' ? 'Pilih Bahasa' : 'Language Selector' }}" class="fixed bottom-5 right-5 z-50 print:hidden">
    <div class="flex items-center bg-white/95 backdrop-blur-md rounded-full shadow-lg hover:shadow-xl border border-slate-200/90 p-1 text-xs font-semibold ring-1 ring-slate-900/5 transition-all duration-200">
        <a href="{{ route('locale.switch', 'en') }}"
           class="flex items-center gap-1.5 px-3 py-1.5 rounded-full transition-all duration-150 {{ $currentLocale === 'en' ? 'bg-[var(--brand)] text-white shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}"
           title="Switch to English"
           aria-label="Switch to English"
           @if($currentLocale === 'en') aria-current="true" @endif>
            <span class="fi fi-gb rounded-[2px] shadow-xs"></span>
            <span>EN</span>
        </a>
        <a href="{{ route('locale.switch', 'id') }}"
           class="flex items-center gap-1.5 px-3 py-1.5 rounded-full transition-all duration-150 {{ $currentLocale === 'id' ? 'bg-[var(--brand)] text-white shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}"
           title="Ganti ke Bahasa Indonesia"
           aria-label="Ganti ke Bahasa Indonesia"
           @if($currentLocale === 'id') aria-current="true" @endif>
            <span class="fi fi-id rounded-[2px] shadow-xs"></span>
            <span>ID</span>
        </a>
    </div>
</aside>
