<?php

namespace App\Http\Controllers\Author;

use App\Filament\Author\Resources\Papers\PaperResource;
use App\Filament\Author\Resources\Registrations\RegistrationResource;
use App\Http\Controllers\Controller;
use App\Models\Author;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    /** Langkah 1: pilih peran (Presenter / Peserta Seminar). Kategori dipilih di form. */
    public function showChoose(Request $request): View|RedirectResponse
    {
        if (in_array($request->query('role'), ['presenter', 'non_presenter'], true)) {
            return redirect()->route('author.register.terms', ['role' => $request->query('role')]);
        }

        return view('author.auth.choose');
    }

    /** Langkah 2: tampilkan Syarat & Ketentuan sesuai peran untuk disetujui. */
    public function showTerms(Request $request): View|RedirectResponse
    {
        $role = $request->query('role');

        if (! in_array($role, ['presenter', 'non_presenter'], true)) {
            return redirect()->route('author.register');
        }

        return view('author.auth.terms', compact('role'));
    }

    /** Tombol "Setuju" pada halaman T&C → catat persetujuan di sesi, lalu ke form. */
    public function acceptTerms(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:presenter,non_presenter'],
        ]);

        $request->session()->put('author_terms_ok', $data['role']);

        return redirect()->route('author.register.start', ['role' => $data['role']]);
    }

    /** Langkah 3: form isian data. Kategori (mahasiswa/dosen/international) dipilih di sini. */
    public function showRegister(Request $request): View|RedirectResponse
    {
        $role = $request->query('role');

        if (! in_array($role, ['presenter', 'non_presenter'], true)) {
            return redirect()->route('author.register');
        }

        // Syarat & Ketentuan wajib disetujui lebih dulu.
        if ($request->session()->get('author_terms_ok') !== $role) {
            return redirect()->route('author.register.terms', ['role' => $role]);
        }

        return view('author.auth.register', compact('role'));
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:authors,email'],
            'affiliation' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
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
