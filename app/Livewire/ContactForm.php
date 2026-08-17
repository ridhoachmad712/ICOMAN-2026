<?php

namespace App\Livewire;

use App\Models\ContactMessage;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ContactForm extends Component
{
    #[Validate('required|min:2|max:255')]
    public string $name = '';

    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate('nullable|max:255')]
    public string $subject = '';

    #[Validate('required|min:5|max:5000')]
    public string $message = '';

    /** Honeypot — harus tetap kosong (diisi hanya oleh bot). */
    public string $website = '';

    public bool $sent = false;

    public function submit(): void
    {
        // Bot terdeteksi: pura-pura sukses, jangan simpan apa pun.
        if ($this->website !== '') {
            $this->sent = true;

            return;
        }

        $key = 'contact-form:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('message', __('Terlalu banyak percobaan. Silakan coba beberapa saat lagi.'));

            return;
        }

        $data = $this->validate();
        RateLimiter::hit($key, 3600);

        ContactMessage::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'] ?: null,
            'message' => $data['message'],
        ]);

        $this->reset(['name', 'email', 'subject', 'message']);
        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.contact-form');
    }
}
