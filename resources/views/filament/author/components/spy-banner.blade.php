@if(app(\App\Services\SpySession::class)->active())
    {{-- Gaya sebaris: banner ini tidak boleh bergantung pada build tema portal. --}}
    <div role="status" style="position:sticky;top:0;z-index:50;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:.75rem;padding:.625rem 1rem;background:#92400e;color:#fff;font-size:.875rem;line-height:1.25rem;">
        <span>
            <strong>Mode lihat.</strong>
            Anda melihat akun <strong>{{ auth('author')->user()?->name }}</strong> sebagai {{ app(\App\Services\SpySession::class)->adminName() }}. Perubahan tidak disimpan.
        </span>
        <form method="POST" action="{{ route('spy.stop') }}" style="margin:0;">
            @csrf
            <button type="submit" style="padding:.25rem .75rem;border-radius:.375rem;background:#fff;color:#92400e;font-weight:600;cursor:pointer;">Keluar dari akun ini</button>
        </form>
    </div>
@endif
