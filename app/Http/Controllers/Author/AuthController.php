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
    /** Langkah 1: pilih jenis registrasi (4 opsi: peran × kategori). */
    public function showChoose(): View
    {
        return view('author.auth.choose');
    }

    /** Langkah 2: form isian data, dengan jenis registrasi yang sudah dipilih. */
    public function showRegister(Request $request): View|RedirectResponse
    {
        $role = $request->query('role');
        $category = $request->query('category');

        if (! in_array($role, ['presenter', 'non_presenter'], true)
            || ! in_array($category, ['student_s1', 'general'], true)) {
            return redirect()->route('author.register');
        }

        return view('author.auth.register', compact('role', 'category'));
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
            'registrant_category' => ['required', 'in:student_s1,general'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $author = Author::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'affiliation' => $data['affiliation'] ?? null,
            'country' => $data['country'] ?? null,
            'phone' => $data['phone'] ?? null,
            'participation_type' => $data['participation_type'] === 'non_presenter' ? 'participant' : 'presenter',
            'registrant_category' => $data['registrant_category'],
            'password' => Hash::make($data['password']),
        ]);

        Auth::guard('author')->login($author);

        if ($data['participation_type'] === 'presenter') {
            return redirect()
                ->to(PaperResource::getUrl('create', panel: 'author'))
                ->with('status', app()->getLocale() === 'id'
                    ? 'Akun berhasil didaftarkan! Lengkapi data paper untuk mulai menulis extended abstract.'
                    : 'Account registered successfully! Complete the paper details to start writing your extended abstract.');
        }

        return redirect()
            ->to(RegistrationResource::getUrl('create', panel: 'author'))
            ->with('status', app()->getLocale() === 'id'
                ? 'Akun berhasil didaftarkan! Silakan pilih paket kepesertaan Seminar Internasional.'
                : 'Account registered successfully! Please choose your International Seminar registration package.');
    }

    // Login & logout author ditangani panel Filament (filament.author.auth.*).
    // Metode Blade lama (showLogin/login/logout) sudah dihapus.
}
