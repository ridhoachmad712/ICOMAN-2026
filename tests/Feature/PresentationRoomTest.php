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
use App\Filament\Widgets\ModeratorDashboard;
use App\Models\Author;
use App\Models\Edition;
use App\Models\PresentationRoom;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\RoomPresentation;
use App\Models\Schedule;
use App\Models\Submission;
use App\Models\Topic;
use App\Models\User;
use App\Services\AbstractWordDocument;
use App\Services\PresentationResultExport;
use App\Services\RoomManager;
use App\Settings\SiteSettings;
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
        $underReview = $this->paper(topic: $marketing, status: 'extended_abstract_submitted');

        $this->assertSame(['assigned' => 6, 'left' => 0], app(RoomManager::class)->distribute($this->edition));

        $this->assertSame([3, 2], [$roomA->presentations()->count(), $roomB->presentations()->count()]);
        $this->assertTrue($marketingPapers->every(fn (Submission $paper) => in_array($paper->roomPresentation->presentation_room_id, [$roomA->id, $roomB->id], true)));
        $this->assertSame($mixed->id, $financePaper->roomPresentation->presentation_room_id);

        // Cukup accepted: belum lunas atau belum full paper tetap dapat room.
        $this->assertNotNull($unpaid->roomPresentation()->first());
        $this->assertNotNull($noFullPaper->roomPresentation()->first());
        $this->assertNull($underReview->roomPresentation()->first());
        $this->expectValidation(fn () => app(RoomManager::class)->assign($roomA, $underReview));

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

        $this->expectValidation(fn () => $manager->score($first, $this->scores(4), null, $moderator));

        $manager->markAttendance($first, 'present', 'Rina', $moderator);
        // Rubrik: skor x bobot / 5 → 25 + 16 + 16 + 9 + 20.
        $manager->score($first->refresh(), ['mastery' => 5, 'structure' => 4, 'communication' => 4, 'slides' => 3, 'qa' => 5], 'Bagus', $moderator);
        $first->refresh();
        $this->assertSame('86.00', (string) $first->presenter_score);
        $this->assertSame(['mastery' => 5, 'structure' => 4, 'communication' => 4, 'slides' => 3, 'qa' => 5], $first->presenter_scores);

        $this->expectValidation(fn () => $manager->score($first, ['mastery' => 6] + $this->scores(3), null, $moderator));
        $this->expectValidation(fn () => $manager->score($first, ['qa' => 0] + $this->scores(3), null, $moderator));

        // Satu presenter belum diabsen: belum boleh dikirim.
        $this->expectValidation(fn () => $manager->submitScores($room, $moderator));

        $manager->markAttendance($second, 'absent', null, $moderator);
        $manager->submitScores($room->refresh(), $moderator);
        $this->assertTrue($room->refresh()->isLocked());

        $this->expectValidation(fn () => $manager->markAttendance($first->refresh(), 'absent', null, $moderator));

        $manager->reopen($room);
        $manager->markAttendance($first->refresh(), 'absent', null, $moderator);
        $this->assertNull($first->refresh()->presenter_score, 'Yang tidak hadir tidak menyimpan nilai presenter.');
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
                'scores' => $this->scores(5),
                'notes' => 'Jelas',
            ])
            ->assertHasNoFormErrors()
            ->callAction(TestAction::make('submitScores'))
            ->assertNotified('Penilaian Room Saya terkirim');

        $mine->refresh();
        $this->assertSame('present', $mine->attendance);
        $this->assertSame('100.00', (string) $mine->presenter_score);
        $this->assertTrue($mine->room->isLocked());
    }

    public function test_submitting_stays_disabled_until_every_presenter_is_finished(): void
    {
        $moderator = $this->user('moderator_room');
        $room = $this->room('Room Saya', moderator: $moderator);
        $manager = app(RoomManager::class);
        $this->actingAs($moderator, 'web');

        // Room kosong tidak bisa dikirim.
        Livewire::test(ListModeratorRooms::class)->assertActionDisabled('submitScores');
        $this->expectValidation(fn () => $manager->submitScores($room, $moderator));

        $attending = $manager->assign($room, $this->paper());
        $absent = $manager->assign($room, $this->paper());

        Livewire::test(ListModeratorRooms::class)
            ->assertActionDisabled('submitScores')
            ->assertSee('2 presenter belum diabsen');

        $manager->markAttendance($attending, 'present', 'Rina', $moderator);
        $manager->markAttendance($absent, 'absent', null, $moderator);

        $page = Livewire::test(ListModeratorRooms::class)
            ->assertActionDisabled('submitScores')
            ->assertSee('1 presenter hadir belum dinilai');
        $page->callAction(TestAction::make('submitScores'));
        $this->assertFalse($room->refresh()->isLocked());

        $manager->score($attending->refresh(), $this->scores(4), null, $moderator);

        Livewire::test(ListModeratorRooms::class)
            ->assertActionEnabled('submitScores')
            ->assertSee('Siap dikirim')
            ->assertSee('1 presenter tidak hadir')
            ->callAction(TestAction::make('submitScores'))
            ->assertNotified('Penilaian Room Saya terkirim');

        $this->assertTrue($room->refresh()->isLocked());
    }

    public function test_only_finished_rooms_can_be_chosen_when_a_moderator_has_several(): void
    {
        $moderator = $this->user('moderator_room');
        $manager = app(RoomManager::class);
        $finished = $this->room('Room Selesai', moderator: $moderator);
        $pending = $this->room('Room Belum', moderator: $moderator);
        $presenter = $manager->assign($finished, $this->paper());
        $manager->assign($pending, $this->paper());
        $manager->markAttendance($presenter, 'absent', null, $moderator);
        $this->actingAs($moderator, 'web');

        Livewire::test(ListModeratorRooms::class)
            ->assertActionEnabled('submitScores')
            ->callAction(TestAction::make('submitScores'), ['room_id' => $finished->id])
            ->assertNotified('Penilaian Room Selesai terkirim');

        $this->assertTrue($finished->refresh()->isLocked());
        $this->assertFalse($pending->refresh()->isLocked());
    }

    public function test_the_moderator_dashboard_shows_the_conference_their_rooms_and_the_rubric(): void
    {
        $this->edition->update(['theme' => ['en' => 'Tema Uji'], 'start_date' => '2026-11-07']);
        app(SiteSettings::class)->fill(['conference_name' => 'Konferensi Uji', 'zoom_meeting_id' => '123 456', 'zoom_passcode' => 'rahasia'])->save();
        Schedule::create([
            'edition_id' => $this->edition->id, 'day_date' => '2026-11-07', 'time_start' => '13:00', 'time_end' => '16:00',
            'title' => ['en' => 'Parallel Session'], 'order' => 1,
        ]);
        $moderator = $this->user('moderator_room');
        app(RoomManager::class)->assign($this->room('Room Dua', moderator: $moderator), $this->paper());
        $this->room('Room Orang Lain', moderator: $this->user('moderator_room'));
        $this->actingAs($moderator, 'web');

        $this->get('/admin')->assertOk()
            ->assertSee('Konferensi Uji')
            ->assertSee('Tema Uji')
            ->assertSee('123 456')
            ->assertSee('Room Dua')
            ->assertSee('0 dari 1 presenter selesai')
            ->assertDontSee('Room Orang Lain')
            ->assertSee(RoomPresentation::PRESENTER_CRITERIA['mastery']['label'])
            ->assertSee('Parallel Session');
    }

    public function test_other_roles_do_not_see_the_moderator_dashboard(): void
    {
        $this->actingAs($this->user('admin_room'), 'web');

        $this->assertFalse(ModeratorDashboard::canView());
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
        app(RoomManager::class)->score($presentation->refresh(), $this->scores(4), null, $admin);
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
        $this->assertEquals(4, $row['Penguasaan materi (skor 1–5, bobot 25%)']);
        $this->assertEquals(80, $row['Nilai presenter (0–100)']);
        $this->assertArrayNotHasKey('Nilai paper (0–100)', $row);
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

    private function paper(?Topic $topic = null, bool $paid = true, bool $fullPaper = true, string $status = 'accepted'): Submission
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
            'status' => $status, 'loa_issued_at' => now(),
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
        return array_fill_keys(array_keys(RoomPresentation::PRESENTER_CRITERIA), $value);
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
