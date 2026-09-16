<?php

namespace App\Http\Controllers\Author;

use App\Filament\Author\Resources\Papers\PaperResource;
use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\CoHost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    /** Langkah 1: pilih peran (Presenter / Peserta Seminar). Kategori dipilih di form. */
    /** Peran yang bisa mendaftar sendiri lewat website. */
    public const ROLES = ['presenter', 'non_presenter', 'cohost'];

    public function showChoose(Request $request): View|RedirectResponse
    {
        if (in_array($request->query('role'), self::ROLES, true)) {
            return redirect()->route('author.register.terms', ['role' => $request->query('role')]);
        }

        return view('author.auth.choose');
    }

    /** Langkah 2: tampilkan Syarat & Ketentuan sesuai peran untuk disetujui. */
    public function showTerms(Request $request): View|RedirectResponse
    {
        $role = $request->query('role');

        if (! in_array($role, self::ROLES, true)) {
            return redirect()->route('author.register');
        }

        return view('author.auth.terms', compact('role'));
    }

    /** Tombol "Setuju" pada halaman T&C → catat persetujuan di sesi, lalu ke form. */
    public function acceptTerms(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:'.implode(',', self::ROLES)],
        ]);

        $request->session()->put('author_terms_ok', $data['role']);

        return redirect()->route('author.register.start', ['role' => $data['role']]);
    }

    /** Langkah 3: form isian data. Kategori (mahasiswa/dosen/international) dipilih di sini. */
    public function showRegister(Request $request): View|RedirectResponse
    {
        $role = $request->query('role');

        if (! in_array($role, self::ROLES, true)) {
            return redirect()->route('author.register');
        }

        // Syarat & Ketentuan wajib disetujui lebih dulu.
        if ($request->session()->get('author_terms_ok') !== $role) {
            return redirect()->route('author.register.terms', ['role' => $role]);
        }

        // Institusi mengisi formulir tersendiri: identitasnya lembaga, bukan orang.
        return view($role === 'cohost' ? 'author.auth.register-cohost' : 'author.auth.register', compact('role'));
    }

    /**
     * Pengajuan institusi co-host.
     *
     * Berbeda dari peserta, akunnya tidak langsung berjalan: satu co-host
     * berarti beberapa paper gratis, jadi pengajuannya menunggu tinjauan
     * panitia lebih dulu.
     */
    public function registerCoHost(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'institution_name' => ['required', 'string', 'max:255'],
            'institution_type' => ['required', Rule::in(array_keys(CoHost::TYPES))],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'name' => ['required', 'string', 'max:255'],
            'pic_position' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:authors,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', Rule::in(array_keys(countryOptions()))],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($request->session()->get('author_terms_ok') !== 'cohost') {
            return redirect()->route('author.register.terms', ['role' => 'cohost']);
        }

        $edition = currentEdition();

        if (! $edition) {
            return back()->withInput()->with('error', app()->getLocale() === 'id'
                ? 'Belum ada edisi konferensi aktif. Hubungi panitia.'
                : 'There is no active conference edition. Please contact the committee.');
        }

        $coHost = DB::transaction(function () use ($data, $edition, $request) {
            $author = Author::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'affiliation' => $data['institution_name'],
                'country' => $data['country'] ?? null,
                'phone' => $data['phone'] ?? null,
                'participation_type' => 'cohost',
                // Tarif co-host tidak mengenal kategori peserta; diisi agar
                // kolomnya tidak kosong dan laporan tetap terbaca.
                'registrant_category' => 'general',
                'terms_accepted_at' => now(),
                'terms_version' => '2026-09-05',
                'terms_locale' => app()->getLocale(),
                'password' => Hash::make($data['password']),
            ]);

            $coHost = CoHost::create([
                'author_id' => $author->id,
                'edition_id' => $edition->id,
                'institution_name' => $data['institution_name'],
                'institution_type' => $data['institution_type'],
                'country' => $data['country'] ?? null,
                'website' => $data['website'] ?? null,
                'pic_position' => $data['pic_position'] ?? null,
                'status' => 'pending',
            ]);

            if ($request->hasFile('logo')) {
                $coHost->addMediaFromRequest('logo')->toMediaCollection('logo');
            }

            return $coHost;
        });

        $request->session()->forget('author_terms_ok');

        Auth::guard('author')->login($coHost->author);

        return redirect()
            ->route('filament.author.pages.author-dashboard')
            ->with('status', app()->getLocale() === 'id'
                ? 'Pengajuan co-host terkirim. Panitia akan meninjaunya dan Anda mendapat kabar lewat email.'
                : 'Your co-host application has been submitted. The committee will review it and let you know by email.');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:authors,email'],
            'affiliation' => ['nullable', 'string', 'max:255'],
            // Negara kini dipilih dari daftar, jadi hanya kode ISO2 yang dikenal yang diterima.
            'country' => ['nullable', 'string', Rule::in(array_keys(countryOptions()))],
            'phone' => ['nullable', 'string', 'max:50'],
            'participation_type' => ['required', 'in:presenter,non_presenter'],
            'registrant_category' => ['required', 'in:student_s1,general,international'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Syarat & Ketentuan wajib disetujui (dicatat di sesi pada langkah T&C).
        if ($request->session()->get('author_terms_ok') !== $data['participation_type']) {
            return redirect()->route('author.register.terms', ['role' => $data['participation_type']]);
        }

        $author = Author::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'affiliation' => $data['affiliation'] ?? null,
            'country' => $data['country'] ?? null,
            'phone' => $data['phone'] ?? null,
            'participation_type' => $data['participation_type'] === 'non_presenter' ? 'participant' : 'presenter',
            'registrant_category' => $data['registrant_category'],
            'terms_accepted_at' => now(),
            'terms_version' => '2026-09-05',
            'terms_locale' => app()->getLocale(),
            'password' => Hash::make($data['password']),
        ]);

        $request->session()->forget('author_terms_ok');

        Auth::guard('author')->login($author);

        if ($data['participation_type'] === 'presenter') {
            return redirect()
                ->to(PaperResource::getUrl('create', panel: 'author'))
                ->with('status', app()->getLocale() === 'id'
                    ? 'Akun berhasil didaftarkan! Lengkapi data paper untuk mulai menulis abstract.'
                    : 'Account registered successfully! Complete the paper details to start writing your abstract.');
        }

        // Invoice peserta dibuat otomatis dari kategori yang dipilih saat mendaftar.
        return redirect()
            ->route('author.registration.checkout')
            ->with('status', app()->getLocale() === 'id'
                ? 'Akun berhasil didaftarkan! Invoice registrasi Anda sudah dibuat — silakan selesaikan pembayaran.'
                : 'Account registered successfully! Your registration invoice is ready — please complete the payment.');
    }

    // Login & logout author ditangani panel Filament (filament.author.auth.*).
    // Metode Blade lama (showLogin/login/logout) sudah dihapus.
}
