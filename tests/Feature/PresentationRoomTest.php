<?php

namespace Tests\Feature;

use App\Filament\Author\Pages\AuthorDashboard;
use App\Filament\Resources\ModeratorRooms\ModeratorRoomResource;
use App\Filament\Resources\ModeratorRooms\Pages\ListModeratorRooms;
use App\Filament\Resources\PresentationResults\Pages\ListPresentationResults;
use App\Filament\Resources\PresentationResults\PresentationResultResource;
use App\Filament\Resources\PresentationRooms\Pages\ListPresentationRooms;
use App\Filament\Resources\PresentationRooms\PresentationRoomResource;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Models\Author;
use App\Models\Edition;
use App\Models\PresentationRoom;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\RoomPresentation;
use App\Models\Submission;
use App\Models\Topic;
use App\Models\User;
use App\Services\AbstractWordDocument;
use App\Services\PresentationResultExport;
use App\Services\RoomManager;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Room presentasi hari H: admin_room membagi presenter dan menetapkan
 * pemenang, moderator_room mengabsen dan menilai di room-nya sendiri.
 */
class PresentationRoomTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('papers');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        foreach (['admin_room', 'moderator_room', 'admin_registrasi'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->edition = Edition::create([
            'name' => 'ICOMAN 2026', 'is_active' => true,
            'theme' => ['en' => 'Theme', 'id' => 'Tema'],
            'start_date' => '2026-11-07', 'end_date' => '2026-11-07',
        ]);
    }

    // Satu akun per tes: AuthenticateSession mengeluarkan sesi yang berganti akun.
    public function test_admin_room_sees_room_management_and_recap_only(): void
    {
        $this->actingAs($this->user('admin_room'), 'web');
        $this->assertTrue(PresentationRoomResource::canAccess());
        $this->assertTrue(PresentationResultResource::canAccess());
        $this->assertFalse(ModeratorRoomResource::canAccess());
        $this->assertFalse(SubmissionResource::canAccess());
        $this->get(PresentationRoomResource::getUrl())->assertOk();
        $this->get(PresentationResultResource::getUrl())->assertOk();
        $this->get(ModeratorRoomResource::getUrl())->assertForbidden();
    }

    public function test_moderator_sees_only_my_room(): void
    {
        $this->actingAs($this->user('moderator_room'), 'web');
        $this->assertTrue(ModeratorRoomResource::canAccess());
        $this->assertFalse(PresentationRoomResource::canAccess());
        $this->assertFalse(PresentationResultResource::canAccess());
        $this->get(ModeratorRoomResource::getUrl())->assertOk();
        $this->get(PresentationRoomResource::getUrl())->assertForbidden();
    }

    public function test_other_roles_do_not_see_conference_menus(): void
    {
        $this->actingAs($this->user('admin_registrasi'), 'web');
        $this->assertFalse(PresentationRoomResource::canAccess());
        $this->assertFalse(ModeratorRoomResource::canAccess());
    }

    public function test_auto_distribution_groups_by_sub_theme_and_balances_rooms(): void
    {
        $marketing = Topic::create(['edition_id' => $this->edition->id, 'title' => ['en' => 'Marketing'], 'order' => 1]);
        $finance = Topic::create(['edition_id' => $this->edition->id, 'title' => ['en' => 'Finance'], 'order' => 2]);

        $roomA = $this->room('Room A', topic: $marketing);
        $roomB = $this->room('Room B', topic: $marketing);
        $mixed = $this->room('Room C');

        $marketingPapers = collect(range(1, 3))->map(fn () => $this->paper(topic: $marketing));
        $financePaper = $this->paper(topic: $finance);
        $unpaid = $this->paper(topic: $marketing, paid: false);
        $noFullPaper = $this->paper(topic: $marketing, fullPaper: false);

        $this->assertSame(['assigned' => 4, 'left' => 0], app(RoomManager::class)->distribute($this->edition));

        $this->assertSame([2, 1], [$roomA->presentations()->count(), $roomB->presentations()->count()]);
        $this->assertTrue($marketingPapers->every(fn (Submission $paper) => in_array($paper->roomPresentation->presentation_room_id, [$roomA->id, $roomB->id], true)));
        $this->assertSame($mixed->id, $financePaper->roomPresentation->presentation_room_id);
        $this->assertNull($unpaid->roomPresentation()->first());
        $this->assertNull($noFullPaper->roomPresentation()->first());

        // Dijalankan lagi: yang sudah punya room tidak dipindahkan.
        $this->assertSame(['assigned' => 0, 'left' => 0], app(RoomManager::class)->distribute($this->edition));
    }

    public function test_moderators_work_only_in_their_own_room_and_scores_lock_after_submission(): void
    {
        $moderator = $this->user('moderator_room');
        $other = $this->user('moderator_room');
        $room = $this->room('Room 1', moderator: $moderator);
        $first = app(RoomManager::class)->assign($room, $this->paper());
        $second = app(RoomManager::class)->assign($room, $this->paper());
        $manager = app(RoomManager::class);

        try {
            $manager->markAttendance($first, 'present', 'X', $other);
            $this->fail('Moderator room lain tidak boleh mengabsen.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->expectValidation(fn () => $manager->score($first, $this->scores(80), $this->scores(90), null, $moderator));

        $manager->markAttendance($first, 'present', 'Rina', $moderator);
        $manager->score($first->refresh(), ['novelty' => 80, 'methodology' => 90, 'contribution' => 70, 'findings' => 60], $this->scores(95), 'Bagus', $moderator);
        $first->refresh();
        $this->assertSame('75.00', (string) $first->paper_score);
        $this->assertSame('95.00', (string) $first->presenter_score);

        $this->expectValidation(fn () => $manager->score($first, ['novelty' => 101] + $this->scores(50), $this->scores(50), null, $moderator));

        // Satu presenter belum diabsen: belum boleh dikirim.
        $this->expectValidation(fn () => $manager->submitScores($room, $moderator));

        $manager->markAttendance($second, 'absent', null, $moderator);
        $manager->submitScores($room->refresh(), $moderator);
        $this->assertTrue($room->refresh()->isLocked());

        $this->expectValidation(fn () => $manager->markAttendance($first->refresh(), 'absent', null, $moderator));

        $manager->reopen($room);
        $manager->markAttendance($first->refresh(), 'absent', null, $moderator);
        $this->assertNull($first->refresh()->paper_score, 'Yang tidak hadir tidak menyimpan nilai.');
    }

    public function test_winners_are_three_per_category_across_all_rooms_and_must_have_attended(): void
    {
        $manager = app(RoomManager::class);
        $admin = $this->user('admin_room');
        $first = $manager->assign($this->room('Room 1'), $this->paper());
        $second = $manager->assign($this->room('Room 2'), $this->paper());
        $absent = $manager->assign(PresentationRoom::where('name', 'Room 2')->sole(), $this->paper());

        $manager->markAttendance($first, 'present', 'A', $admin);
        $manager->markAttendance($second, 'represented', 'B', $admin);
        $manager->markAttendance($absent, 'absent', null, $admin);

        $manager->setRank($first->refresh(), 'best_paper', 1);
        $manager->setRank($second->refresh(), 'best_paper', 1);
        $this->assertNull($first->refresh()->best_paper_rank, 'Juara 1 pindah ke paper yang baru ditetapkan.');
        $this->assertSame(1, $second->refresh()->best_paper_rank);

        $manager->setRank($first, 'best_presenter', 3);
        $this->assertSame(3, $first->refresh()->best_presenter_rank);

        $this->expectValidation(fn () => $manager->setRank($first, 'best_paper', 4));
        $this->expectValidation(fn () => $manager->setRank($absent->refresh(), 'best_paper', 2));
    }

    public function test_moderator_page_lists_only_their_room_and_records_attendance_and_scores(): void
    {
        $moderator = $this->user('moderator_room');
        $mine = app(RoomManager::class)->assign($this->room('Room Saya', moderator: $moderator), $this->paper());
        $theirs = app(RoomManager::class)->assign($this->room('Room Lain', moderator: $this->user('moderator_room')), $this->paper());
        $this->actingAs($moderator, 'web');

        Livewire::test(ListModeratorRooms::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs])
            ->assertActionHidden(TestAction::make('score')->table($mine))
            ->callAction(TestAction::make('attendance')->table($mine), ['attendance' => 'present', 'presenter_name' => $mine->submission->authors->first()->name])
            ->assertHasNoFormErrors()
            ->callAction(TestAction::make('score')->table($mine), [
                'paper' => $this->scores(80),
                'presenter' => $this->scores(70),
                'notes' => 'Jelas',
            ])
            ->assertHasNoFormErrors()
            ->callAction(TestAction::make('submitScores'))
            ->assertNotified('Penilaian Room Saya terkirim');

        $mine->refresh();
        $this->assertSame('present', $mine->attendance);
        $this->assertSame('80.00', (string) $mine->paper_score);
        $this->assertTrue($mine->room->isLocked());
    }

    public function test_admin_room_distributes_and_awards_from_the_panel(): void
    {
        $this->actingAs($this->user('admin_room'), 'web');
        $this->room('Room 1');
        $paper = $this->paper();

        Livewire::test(ListPresentationRooms::class)
            ->callAction(TestAction::make('distribute'))
            ->assertNotified('1 paper dibagikan ke room');

        $presentation = $paper->roomPresentation()->sole();
        app(RoomManager::class)->markAttendance($presentation, 'present', 'Rina', auth()->user());

        Livewire::test(ListPresentationResults::class)
            ->callAction(TestAction::make('award')->table($presentation), ['best_paper' => 2, 'best_presenter' => null])
            ->assertNotified('Pemenang tersimpan');

        $this->assertSame(2, $presentation->refresh()->best_paper_rank);
        $this->assertNull($presentation->best_presenter_rank);
    }

    public function test_presenters_see_their_room_only_after_it_is_published(): void
    {
        $paper = $this->paper();
        $room = $this->room('Room Hijau');
        $room->update(['meeting_url' => 'https://zoom.example.test/room-hijau', 'starts_at' => '2026-11-07 09:00']);
        app(RoomManager::class)->assign($room, $paper);
        $dashboard = AuthorDashboard::getUrl(panel: 'author');

        $this->actingAs($paper->author, 'author')->get($dashboard)->assertOk()->assertDontSee('Room Hijau');

        $room->update(['is_published' => true]);
        $this->actingAs($paper->author, 'author')->get($dashboard)
            ->assertOk()
            ->assertSee('Room Hijau')
            ->assertSee('https://zoom.example.test/room-hijau', false);
    }

    public function test_the_recap_export_has_per_criterion_scores_and_winners(): void
    {
        $admin = $this->user('admin_room');
        $presentation = app(RoomManager::class)->assign($this->room('Room 1'), $this->paper());
        app(RoomManager::class)->markAttendance($presentation, 'present', 'Rina', $admin);
        app(RoomManager::class)->score($presentation->refresh(), $this->scores(88), $this->scores(77), null, $admin);
        app(RoomManager::class)->setRank($presentation->refresh(), 'best_paper', 1);

        $export = app(PresentationResultExport::class);
        $path = $export->create(RoomPresentation::query());

        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();
        unlink($path);

        $this->assertSame(PresentationResultExport::headings(), $rows[0]);
        $row = array_combine($rows[0], $rows[1]);
        $this->assertSame('Hadir', $row['Kehadiran']);
        $this->assertEquals(88, $row['Kebaruan dan orisinalitas']);
        $this->assertEquals(88, $row['Nilai paper']);
        $this->assertEquals(77, $row['Nilai presenter']);
        $this->assertSame('Juara 1', $row['Best Paper']);
    }

    private function user(string $role): User
    {
        $user = User::create(['name' => ucfirst($role).' '.uniqid(), 'email' => uniqid($role).'@example.test', 'password' => 'secret-password']);
        $user->assignRole($role);

        return $user;
    }

    private function room(string $name, ?Topic $topic = null, ?User $moderator = null): PresentationRoom
    {
        return PresentationRoom::create([
            'edition_id' => $this->edition->id, 'name' => $name,
            'topic_id' => $topic?->id, 'moderator_id' => $moderator?->id,
            'order' => PresentationRoom::count() + 1,
        ]);
    }

    private function paper(?Topic $topic = null, bool $paid = true, bool $fullPaper = true): Submission
    {
        $author = Author::create([
            'name' => 'Penulis '.uniqid(), 'email' => fake()->unique()->safeEmail(), 'password' => 'secret-password',
            'affiliation' => 'UNM', 'participation_type' => 'presenter', 'registrant_category' => 'general',
        ]);

        $fee = RegistrationFee::firstOrCreate(
            ['edition_id' => $this->edition->id, 'audience' => 'presenter', 'registrant_category' => 'general'],
            ['category' => ['en' => 'Presenter'], 'price_regular' => 50_000, 'currency' => 'IDR'],
        );
        Registration::create([
            'edition_id' => $this->edition->id, 'author_id' => $author->id, 'registration_fee_id' => $fee->id,
            'amount' => 50_000, 'currency' => 'IDR', 'payment_method' => 'manual',
            'status' => $paid ? 'paid' : 'pending', 'paid_at' => $paid ? now() : null,
        ]);

        $submission = Submission::create([
            'edition_id' => $this->edition->id, 'author_id' => $author->id, 'topic_id' => $topic?->id,
            'title' => 'Paper '.uniqid(), 'abstract' => 'Abstract', 'keywords' => ['k'],
            'status' => 'accepted', 'loa_issued_at' => now(),
        ]);
        $submission->authors()->create([
            'name' => $author->name, 'email' => $author->email, 'affiliation' => 'UNM', 'is_corresponding' => true, 'order' => 1,
        ]);

        if ($fullPaper) {
            $docx = app(AbstractWordDocument::class)->create($submission);
            $submission->addMedia(UploadedFile::fake()->createWithContent('naskah.docx', file_get_contents($docx)))
                ->toMediaCollection('camera_ready');
        }

        return $submission->refresh();
    }

    /** @return array<string, int> */
    private function scores(int $value): array
    {
        return array_fill_keys(array_keys(RoomPresentation::PAPER_CRITERIA + RoomPresentation::PRESENTER_CRITERIA), $value);
    }

    private function expectValidation(callable $callback): void
    {
        try {
            $callback();
            $this->fail('ValidationException diharapkan.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }
}
