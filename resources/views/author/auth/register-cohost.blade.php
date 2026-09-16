<x-author-layout :title="app()->getLocale() === 'id' ? 'Pengajuan Co-host' : 'Co-host Application'">
    @php
        $isId = app()->getLocale() === 'id';
        $input = 'w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--brand)]/20';
        $label = 'mb-1.5 block text-sm font-medium text-slate-700';
    @endphp

    <div class="mx-auto max-w-2xl">
        <div class="card p-6 sm:p-8">
            <div class="mb-6 text-center">
                <span class="text-xs font-semibold uppercase tracking-widest text-[var(--brand-ink)]">
                    {{ siteSettings()->conference_name ?: 'ICOMAN 2026' }}
                </span>
                <h1 class="mt-2 font-display text-2xl font-bold tracking-tight text-[var(--brand-2)]">
                    {{ $isId ? 'Pengajuan Institusi Co-host' : 'Co-host Institution Application' }}
                </h1>
                <p class="mx-auto mt-1.5 max-w-md text-sm leading-relaxed text-slate-500">
                    {{ $isId
                        ? 'Pengajuan ditinjau panitia lebih dulu. Anda akan mendapat kabar lewat email.'
                        : 'Applications are reviewed by the committee first. You will hear back by email.' }}
                </p>
            </div>

            @if($errors->any())
                <div role="alert" class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('author.register.cohost') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <div>
                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-400">
                        {{ $isId ? 'Institusi' : 'Institution' }}
                    </p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="{{ $label }}" for="institution_name">{{ $isId ? 'Nama institusi' : 'Institution name' }} *</label>
                            <input id="institution_name" name="institution_name" value="{{ old('institution_name') }}" required class="{{ $input }}">
                        </div>

                        <div>
                            <label class="{{ $label }}" for="institution_type">{{ $isId ? 'Jenis institusi' : 'Institution type' }} *</label>
                            <select id="institution_type" name="institution_type" required class="{{ $input }}">
                                <option value="">{{ $isId ? 'Pilih jenis' : 'Choose a type' }}</option>
                                @foreach(\App\Models\CoHost::TYPES as $value => $text)
                                    <option value="{{ $value }}" @selected(old('institution_type') === $value)>{{ $text }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="{{ $label }}" for="country">{{ $isId ? 'Negara' : 'Country' }}</label>
                            <select id="country" name="country" class="{{ $input }}">
                                <option value="">{{ $isId ? 'Pilih negara' : 'Choose a country' }}</option>
                                @foreach(countryOptions() as $code => $text)
                                    <option value="{{ $code }}" @selected(old('country') === $code)>{{ $text }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="{{ $label }}" for="website">{{ $isId ? 'Situs web' : 'Website' }}</label>
                            <input id="website" name="website" type="url" value="{{ old('website') }}" placeholder="https://" class="{{ $input }}">
                        </div>

                        <div>
                            <label class="{{ $label }}" for="logo">{{ $isId ? 'Logo institusi' : 'Institution logo' }}</label>
                            <input id="logo" name="logo" type="file" accept="image/*" class="{{ $input }} file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs">
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $isId ? 'Dipakai untuk pencantuman sebagai mitra. Maksimal 2 MB.' : 'Used to credit you as a partner. Maximum 2 MB.' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-200 pt-6">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-400">
                        {{ $isId ? 'Penanggung jawab' : 'Contact person' }}
                    </p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="{{ $label }}" for="name">{{ $isId ? 'Nama lengkap' : 'Full name' }} *</label>
                            <input id="name" name="name" value="{{ old('name') }}" required class="{{ $input }}">
                        </div>

                        <div>
                            <label class="{{ $label }}" for="pic_position">{{ $isId ? 'Jabatan' : 'Position' }}</label>
                            <input id="pic_position" name="pic_position" value="{{ old('pic_position') }}" class="{{ $input }}">
                        </div>

                        <div>
                            <label class="{{ $label }}" for="email">{{ $isId ? 'Email' : 'Email' }} *</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required class="{{ $input }}">
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $isId ? 'Dipakai juga untuk masuk ke portal co-host.' : 'Also used to sign in to the co-host portal.' }}
                            </p>
                        </div>

                        <div>
                            <label class="{{ $label }}" for="phone">{{ $isId ? 'Telepon' : 'Phone' }}</label>
                            <input id="phone" name="phone" value="{{ old('phone') }}" class="{{ $input }}">
                        </div>

                        <div>
                            <label class="{{ $label }}" for="password">{{ $isId ? 'Kata sandi' : 'Password' }} *</label>
                            <input id="password" name="password" type="password" required class="{{ $input }}">
                        </div>

                        <div>
                            <label class="{{ $label }}" for="password_confirmation">{{ $isId ? 'Ulangi kata sandi' : 'Repeat password' }} *</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" required class="{{ $input }}">
                        </div>
                    </div>
                </div>

                <div class="rounded-xl bg-slate-50 p-4 text-xs leading-relaxed text-slate-600">
                    {{ $isId
                        ? 'Setelah disetujui, Anda menerima kode voucher untuk '.\App\Models\CoHost::FREE_PAPERS.' paper gratis beserta invoice biaya kemitraan. Kode dapat dipakai setelah biaya tersebut lunas.'
                        : 'Once approved you receive a voucher code for '.\App\Models\CoHost::FREE_PAPERS.' free papers along with the partnership invoice. The code becomes usable after that fee is settled.' }}
                </div>

                <button type="submit" class="btn btn-primary w-full justify-center">
                    {{ $isId ? 'Kirim Pengajuan' : 'Submit Application' }}
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-500">
                {{ $isId ? 'Sudah punya akun?' : 'Already have an account?' }}
                <a href="{{ route('filament.author.auth.login') }}" class="font-semibold text-[var(--brand-ink)] hover:underline">
                    {{ $isId ? 'Masuk' : 'Sign in' }}
                </a>
            </p>
        </div>
    </div>
</x-author-layout>
