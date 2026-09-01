<?php

namespace Tests\Feature;

use App\Filament\Author\Resources\Papers\PaperResource;
use App\Filament\Author\Resources\Registrations\RegistrationResource;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\Submission;
use Tests\TestCase;

class OwnershipTest extends TestCase
{
    public function test_author_cannot_view_another_authors_submission_or_registration(): void
    {
        $edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $owner = $this->author('owner@example.test');
        $intruder = $this->author('intruder@example.test');
        $submission = Submission::create([
            'edition_id' => $edition->id,
            'author_id' => $owner->id,
            'title' => 'Private paper',
            'abstract' => 'Private abstract',
        ]);
        $fee = RegistrationFee::create([
            'edition_id' => $edition->id,
            'category' => ['en' => 'Presenter'],
            'price_regular' => 500_000,
            'currency' => 'IDR',
        ]);
        $registration = Registration::create([
            'edition_id' => $edition->id,
            'author_id' => $owner->id,
            'registration_fee_id' => $fee->id,
            'payment_method' => 'manual',
            'amount' => 500_000,
            'status' => 'pending',
        ]);

        $this->actingAs($intruder, 'author')
            ->get(route('author.submissions.show', $submission))
            ->assertForbidden();

        $this->actingAs($intruder, 'author')
            ->get(route('author.registration.show', $registration))
            ->assertForbidden();

        $this->actingAs($intruder, 'author')
            ->get(PaperResource::getUrl('view', ['record' => $submission], panel: 'author'))
            ->assertNotFound();

        $this->actingAs($intruder, 'author')
            ->get(RegistrationResource::getUrl('view', ['record' => $registration], panel: 'author'))
            ->assertNotFound();
    }

    private function author(string $email): Author
    {
        return Author::create([
            'name' => 'Test Author',
            'email' => $email,
            'password' => 'secret-password',
        ]);
    }
}
