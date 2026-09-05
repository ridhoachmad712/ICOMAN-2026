<x-author-layout :title="app()->getLocale() === 'id' ? 'Syarat dan Ketentuan' : 'Terms and Conditions'">
    @php $isId = app()->getLocale() === 'id'; $clauses = array_merge(__('terms.common'), __($role === 'presenter' ? 'terms.presenter' : 'terms.participant')); @endphp
    <div class="card mx-auto max-w-3xl p-6 sm:p-8">
        <p class="text-sm font-semibold text-[var(--brand)]">{{ siteSettings()->conference_name }} · {{ $role === 'presenter' ? 'Presenter' : ($isId ? 'Peserta seminar' : 'Seminar attendee') }}</p>
        <h1 class="mt-3 text-2xl font-bold">{{ $isId ? 'Syarat dan Ketentuan' : 'Terms and Conditions' }}</h1>
        <p class="mt-2 text-xs text-slate-500">{{ $isId ? 'Versi' : 'Version' }} 2026-09-05</p>
        <ol class="mt-6 list-decimal space-y-5 pl-5 text-sm leading-7 text-slate-600">
            @foreach($clauses as $clause)<li><strong class="text-slate-900">{{ $clause['title'] }}.</strong> {{ $clause['body'] }}</li>@endforeach
        </ol>
        @if($role === 'presenter')<p class="mt-6 text-sm">{{ $isId ? 'Biaya opsi SINTA 3 saat ini' : 'Current optional SINTA 3 fee' }}: IDR {{ number_format((float) siteSettings()->sinta3_fee, 0, ',', '.') }}.</p>@endif
        <div class="mt-6 flex flex-wrap gap-4 text-sm font-semibold underline">
            <a href="{{ route('author-guidelines', ['lang' => app()->getLocale()]) }}">{{ $isId ? 'Panduan dan deadline' : 'Guidelines and deadlines' }}</a>
            <a href="{{ route('privacy', ['lang' => app()->getLocale()]) }}">{{ $isId ? 'Privasi dan penggunaan data' : 'Privacy and data use' }}</a>
            <a href="{{ route('registration', ['lang' => app()->getLocale()]) }}">{{ $isId ? 'Daftar biaya' : 'Registration fees' }}</a>
        </div>
        <form class="mt-8" method="POST" action="{{ route('author.register.accept-terms') }}">
            @csrf<input type="hidden" name="role" value="{{ $role }}">
            <p class="mb-4 text-xs text-slate-500">{{ $isId ? 'Dengan melanjutkan, Anda menyetujui syarat di atas.' : 'By continuing, you agree to the terms above.' }}</p>
            <button type="submit" class="btn btn-accent w-full">{{ $isId ? 'Setuju dan Lanjutkan' : 'Accept and Continue' }}</button>
        </form>
        <a class="mt-4 block text-center text-sm underline" href="{{ route('author.register') }}">{{ $isId ? 'Kembali pilih peran' : 'Back to role selection' }}</a>
    </div>
</x-author-layout>
