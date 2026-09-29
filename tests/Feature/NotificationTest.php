<?php

namespace Tests\Feature;

use App\Enums\AnnouncementAudience;
use App\Enums\LevelType;
use App\Enums\TrackCategory;
use App\Models\AcademicTerm;
use App\Models\Announcement;
use App\Models\GradeLevel;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TrackStrand;
use App\Models\User;
use App\Notifications\CourseAnnouncementNotification;
use App\Notifications\NewLessonNotification;
use App\Notifications\NewQuizNotification;
use App\Support\StudentNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Notification & email alert system: who gets notified, the queue/transaction
 * behaviour, the emails themselves and the in-app bell.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Subject $subject;
    private Section $sectionA;   // takes the subject
    private Section $sectionB;   // takes the subject
    private Section $sectionC;   // does NOT take the subject
    private User $studentA1;
    private User $studentA2;
    private User $studentB;
    private User $studentC;
    private User $unassigned;    // student with no section

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $term   = AcademicTerm::create(['name' => '1st Term', 'term_number' => 1, 'academic_year' => '2026-2027', 'is_current' => true]);
        $level  = GradeLevel::create(['name' => 'Grade 11', 'level_type' => LevelType::Shs]);
        $strand = TrackStrand::create(['name' => 'ICT', 'category' => TrackCategory::TechPro]);

        $make = fn (string $name) => Section::create(['name' => $name, 'capacity' => 40, 'grade_level_id' => $level->id, 'track_strand_id' => $strand->id]);
        [$this->sectionA, $this->sectionB, $this->sectionC] = [$make('ICT 11-A'), $make('ICT 11-B'), $make('ICT 11-C')];

        $this->subject = Subject::create([
            'code' => 'ICT-PROG1', 'name' => 'Computer Programming 1',
            'grade_level_id' => $level->id, 'track_strand_id' => $strand->id, 'academic_term_id' => $term->id,
        ]);

        $this->teacher = User::factory()->teacher()->create(['name' => 'Maria Santos']);
        $this->subject->assignTeacher($this->teacher, $this->sectionA);
        $this->subject->assignTeacher($this->teacher, $this->sectionB);

        $this->studentA1  = User::factory()->student($this->sectionA)->create(['name' => 'Juan Dela Cruz']);
        $this->studentA2  = User::factory()->student($this->sectionA)->create();
        $this->studentB   = User::factory()->student($this->sectionB)->create();
        $this->studentC   = User::factory()->student($this->sectionC)->create();
        $this->unassigned = User::factory()->student()->create();
    }

    /* ------------------------------------------------------------------ */
    /*  Event triggers & recipients                                       */
    /* ------------------------------------------------------------------ */

    public function test_posting_a_lesson_notifies_only_students_whose_section_takes_the_subject(): void
    {
        Notification::fake();

        $this->actingAs($this->teacher)->post(route('teacher.lessons.store'), [
            'title' => 'Variables and Data Types', 'subject_id' => $this->subject->id, 'content' => 'Intro to variables.',
        ])->assertRedirect()->assertSessionHas('status', 'Lesson published. 3 students notified.');

        Notification::assertSentTo([$this->studentA1, $this->studentA2, $this->studentB], NewLessonNotification::class,
            function (NewLessonNotification $n, array $channels) {
                return $channels === ['database', 'mail']
                    && $n->data['title'] === 'Variables and Data Types'
                    && $n->data['route'] === 'student.lessons.show';
            });
        Notification::assertNotSentTo([$this->studentC, $this->unassigned, $this->teacher], NewLessonNotification::class);
        Notification::assertCount(3);
    }

    public function test_editing_a_lesson_does_not_notify_again(): void
    {
        $lesson = $this->makeLesson();
        Notification::fake();

        $this->actingAs($this->teacher)->put(route('teacher.lessons.update', $lesson), [
            'title' => 'Updated title', 'subject_id' => $this->subject->id,
        ])->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_publishing_a_quiz_notifies_students_in_its_sections_once(): void
    {
        Notification::fake();
        $quiz = $this->makeQuiz([$this->sectionA]);

        $this->actingAs($this->teacher)->patch(route('teacher.quizzes.publish', $quiz))
            ->assertSessionHas('status', fn ($s) => str_ends_with($s, '2 students notified.'));

        Notification::assertSentTo([$this->studentA1, $this->studentA2], NewQuizNotification::class);
        Notification::assertNotSentTo([$this->studentB, $this->studentC], NewQuizNotification::class);

        // Unpublish + publish again: no second alert
        $this->patch(route('teacher.quizzes.publish', $quiz));
        $this->patch(route('teacher.quizzes.publish', $quiz));
        Notification::assertSentToTimes($this->studentA1, NewQuizNotification::class, 1);
    }

    public function test_a_draft_quiz_notifies_nobody(): void
    {
        Notification::fake();
        $this->makeQuiz([$this->sectionA]);

        Notification::assertNothingSent();
    }

    public function test_adding_a_section_to_a_published_quiz_notifies_only_that_section(): void
    {
        $quiz = $this->makeQuiz([$this->sectionA], published: true);
        Notification::fake();

        $this->actingAs($this->teacher)->put(route('teacher.quizzes.update', $quiz), [
            'title' => $quiz->title, 'passing_score' => 75,
            'sections' => [$this->sectionA->id, $this->sectionB->id],
        ])->assertSessionHas('status', 'Quiz settings saved. 1 student notified.');

        Notification::assertSentTo($this->studentB, NewQuizNotification::class);
        Notification::assertNotSentTo([$this->studentA1, $this->studentA2], NewQuizNotification::class);
    }

    public function test_teacher_section_announcement_notifies_that_section_only(): void
    {
        Notification::fake();

        $this->actingAs($this->teacher)->post(route('announcements.store'), [
            'title' => 'No classes on Friday', 'body' => 'Enjoy the long weekend.',
            'audience' => AnnouncementAudience::Section->value, 'section_id' => $this->sectionB->id,
        ])->assertSessionHas('status', 'Announcement posted. 1 student notified.');

        Notification::assertSentTo($this->studentB, CourseAnnouncementNotification::class,
            fn ($n) => $n->data['title'] === 'No classes on Friday');
        Notification::assertCount(1);
    }

    public function test_school_wide_announcements_reach_every_student_but_teacher_only_ones_reach_none(): void
    {
        Notification::fake();
        $developer = User::factory()->developer()->create();

        $this->actingAs($developer)->post(route('announcements.store'), [
            'title' => 'Enrollment is open', 'body' => 'See the registrar.', 'audience' => AnnouncementAudience::Students->value,
        ]);
        Notification::assertCount(5); // all five students, including one without a section
        Notification::assertNotSentTo([$this->teacher, $developer], CourseAnnouncementNotification::class);

        $this->post(route('announcements.store'), [
            'title' => 'Faculty meeting', 'body' => 'Room 101.', 'audience' => AnnouncementAudience::Teachers->value,
        ])->assertSessionHas('status', 'Announcement posted.');
        Notification::assertCount(5);
    }

    public function test_email_alerts_can_be_switched_off(): void
    {
        config(['eduvers.notifications.mail' => false]);
        Notification::fake();

        app(StudentNotifier::class)->lessonPosted($this->makeLesson());

        Notification::assertSentTo($this->studentA1, NewLessonNotification::class,
            fn ($n, array $channels) => $channels === ['database']);
    }

    /* ------------------------------------------------------------------ */
    /*  Queue & transaction behaviour (real channels, no fakes)            */
    /* ------------------------------------------------------------------ */

    public function test_in_app_notifications_are_instant_and_emails_wait_for_the_queue_worker(): void
    {
        config(['queue.default' => 'database']);

        $this->actingAs($this->teacher)->post(route('teacher.lessons.store'), [
            'title' => 'Loops', 'subject_id' => $this->subject->id,
        ])->assertRedirect();

        // Bell: written during the request (database channel runs on "sync")
        $this->assertSame(1, $this->studentA1->unreadNotifications()->count());
        $this->assertSame(3, DatabaseNotification::count());
        $this->assertSame('Loops', $this->studentA1->notifications()->first()->data['title']);

        // Email: one queued job per student, nothing sent yet
        $this->assertSame(3, DB::table('jobs')->count());
        $this->assertCount(0, $this->sentMails());

        $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true])->assertSuccessful();

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());

        $mails = $this->sentMails();
        $this->assertCount(3, $mails);
        $recipients = collect($mails)->map(fn ($m) => $m->getEnvelope()->getRecipients()[0]->getAddress())->sort()->values()->all();
        $this->assertSame(collect([$this->studentA1, $this->studentA2, $this->studentB])->pluck('email')->sort()->values()->all(), $recipients);
        $this->assertStringContainsString('[EduVers] New lesson: Loops', $mails[0]->getOriginalMessage()->getSubject());
    }

    public function test_nothing_is_sent_when_the_transaction_rolls_back(): void
    {
        config(['queue.default' => 'database']);

        try {
            DB::transaction(function () {
                $lesson = $this->makeLesson();
                app(StudentNotifier::class)->lessonPosted($lesson);

                throw new RuntimeException('Simulated failure after saving');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, Lesson::count());
        $this->assertSame(0, DatabaseNotification::count());
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_notifications_are_sent_after_the_transaction_commits(): void
    {
        config(['queue.default' => 'database']);

        DB::transaction(function () {
            app(StudentNotifier::class)->lessonPosted($this->makeLesson());

            // Still inside the transaction: nothing dispatched yet
            $this->assertSame(0, DatabaseNotification::count());
        });

        $this->assertSame(3, DatabaseNotification::count());
        $this->assertSame(3, DB::table('jobs')->count());
    }

    /* ------------------------------------------------------------------ */
    /*  Emails                                                            */
    /* ------------------------------------------------------------------ */

    public function test_email_uses_the_eduvers_theme_and_links_to_the_lesson(): void
    {
        $lesson = $this->makeLesson('Arrays & Lists');
        $html = (string) NewLessonNotification::for($lesson)->toMail($this->studentA1)->render();

        $this->assertStringContainsString('Hi Juan,', $html);
        $this->assertStringContainsString('Arrays &amp; Lists', $html);
        $this->assertStringContainsString('Maria Santos', $html);
        $this->assertStringContainsString(route('student.lessons.show', $lesson), $html);
        $this->assertStringContainsString('Transform · Learn · Lead', $html);
        $this->assertStringContainsString('#D4AF37', $html); // gold button from themes/eduvers.css
    }

    public function test_teacher_text_cannot_inject_links_or_html_into_the_email(): void
    {
        $lesson = $this->makeLesson('[Claim your prize](https://evil.example) <b>now</b>');
        $html = (string) NewLessonNotification::for($lesson)->toMail($this->studentA1)->render();

        $this->assertStringNotContainsString('href="https://evil.example"', $html);
        $this->assertStringNotContainsString('<b>now</b>', $html);
    }

    public function test_quiz_and_announcement_emails_render(): void
    {
        $quiz = $this->makeQuiz([$this->sectionA], published: true);
        $quizHtml = (string) NewQuizNotification::for($quiz)->toMail($this->studentA1)->render();
        $this->assertStringContainsString('Take the quiz', $quizHtml);
        $this->assertStringContainsString(route('student.quizzes.show', $quiz), $quizHtml);

        $announcement = Announcement::create([
            'title' => 'Uniform reminder', 'body' => 'Wear your ID.', 'audience' => AnnouncementAudience::Section,
            'section_id' => $this->sectionA->id, 'author_id' => $this->teacher->id,
        ]);
        $annHtml = (string) CourseAnnouncementNotification::for($announcement)->toMail($this->studentA1)->render();
        $this->assertStringContainsString('Uniform reminder', $annHtml);
        $this->assertStringContainsString(route('student.announcements.show', $announcement), $annHtml);
    }

    public function test_brevo_mailer_sends_over_https(): void
    {
        config(['mail.mailers.brevo.key' => 'test-key', 'mail.from.address' => 'eduvers@example.com', 'mail.from.name' => 'EduVers']);
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => '<abc@brevo>'], 201)]);

        $lesson = $this->makeLesson('Functions');
        $message = NewLessonNotification::for($lesson)->toMail($this->studentA1);
        Mail::mailer('brevo')->html((string) $message->render(), function ($m) {
            $m->to($this->studentA1->email, $this->studentA1->name)->subject('[EduVers] New lesson: Functions');
        });

        Http::assertSent(function (HttpRequest $request) {
            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'test-key')
                && $request['sender'] === ['email' => 'eduvers@example.com', 'name' => 'EduVers']
                && $request['to'] === [['email' => $this->studentA1->email, 'name' => $this->studentA1->name]]
                && $request['subject'] === '[EduVers] New lesson: Functions'
                && str_contains($request['htmlContent'], 'Functions');
        });
    }

    public function test_brevo_errors_are_reported_as_failed_sends(): void
    {
        config(['mail.mailers.brevo.key' => 'bad-key']);
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'Key not found'], 401)]);

        $this->expectExceptionMessage('Brevo API rejected the email (HTTP 401): Key not found');

        Mail::mailer('brevo')->raw('Hello', fn ($m) => $m->to('student@example.com')->subject('Test'));
    }

    /* ------------------------------------------------------------------ */
    /*  In-app bell & notifications page                                  */
    /* ------------------------------------------------------------------ */

    public function test_student_header_shows_the_bell_with_unread_count(): void
    {
        app(StudentNotifier::class)->lessonPosted($this->makeLesson('Loops'));

        $this->actingAs($this->studentA1)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('data-notification-bell', false)
            ->assertSee('Loops')
            ->assertSeeInOrder(['data-notification-badge', '1'], false);

        // Teachers don't get the student bell
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertDontSee('data-notification-bell', false);
    }

    public function test_opening_a_notification_marks_it_read_and_jumps_to_the_item(): void
    {
        $lesson = $this->makeLesson();
        app(StudentNotifier::class)->lessonPosted($lesson);
        $notification = $this->studentA1->notifications()->first();

        $this->actingAs($this->studentA1)->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('student.lessons.show', $lesson));

        $this->assertNotNull($notification->fresh()->read_at);
        $this->get(route('student.lessons.show', $lesson))->assertOk();
    }

    public function test_announcement_notification_opens_the_announcement_page(): void
    {
        $announcement = Announcement::create([
            'title' => 'Exam schedule', 'body' => 'Posted at the lobby.', 'audience' => AnnouncementAudience::Section,
            'section_id' => $this->sectionA->id, 'author_id' => $this->teacher->id,
        ]);
        app(StudentNotifier::class)->announcementPosted($announcement);

        $id = $this->studentA1->notifications()->first()->id;
        $this->actingAs($this->studentA1)->get(route('notifications.open', $id))
            ->assertRedirect(route('student.announcements.show', $announcement));
        $this->get(route('student.announcements.show', $announcement))->assertOk()->assertSee('Posted at the lobby.');

        // Students of other sections can't open it
        $this->actingAs($this->studentB)->get(route('student.announcements.show', $announcement))->assertNotFound();
    }

    public function test_users_cannot_open_or_mark_someone_elses_notifications(): void
    {
        app(StudentNotifier::class)->lessonPosted($this->makeLesson());
        $theirs = $this->studentA1->notifications()->first();

        $this->actingAs($this->studentB)->get(route('notifications.open', $theirs->id))->assertNotFound();
        $this->actingAs($this->studentB)->patch(route('notifications.read', $theirs->id))->assertNotFound();
        $this->assertNull($theirs->fresh()->read_at);

        // Malformed ids are a clean 404 (matters on PostgreSQL's uuid column)
        $this->get('/notifications/not-a-uuid/open')->assertNotFound();
    }

    public function test_notifications_page_lists_filters_and_marks_all_read(): void
    {
        app(StudentNotifier::class)->lessonPosted($this->makeLesson('Loops'));
        app(StudentNotifier::class)->lessonPosted($this->makeLesson('Arrays'));
        $this->studentA1->notifications->firstWhere('data.title', 'Arrays')->markAsRead();

        // Excerpts only appear in the full list (the header dropdown shows titles only)
        $this->actingAs($this->studentA1)->get(route('notifications.index'))
            ->assertOk()->assertSee('Lesson body about loops.')->assertSee('Lesson body about arrays.')->assertSee('Mark all as read');

        $this->get(route('notifications.index', ['filter' => 'unread']))
            ->assertOk()->assertSee('Lesson body about loops.')->assertDontSee('Lesson body about arrays.');

        $this->getJson(route('notifications.count'))->assertJsonPath('unread', 1);

        $this->from(route('notifications.index'))->post(route('notifications.read-all'))
            ->assertRedirect(route('notifications.index'));
        $this->getJson(route('notifications.count'))->assertJsonPath('unread', 0);
    }

    public function test_bell_polling_reports_the_newest_notification_and_serves_a_fresh_dropdown(): void
    {
        $this->actingAs($this->studentA1)->getJson(route('notifications.count'))
            ->assertExactJson(['unread' => 0, 'latest' => null]);

        // A teacher posts while the student's page is open
        app(StudentNotifier::class)->lessonPosted($this->makeLesson('Recursion'));
        $notification = $this->studentA1->notifications()->first();

        $this->getJson(route('notifications.count'))
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('latest.id', $notification->id)
            ->assertJsonPath('latest.unread', true)
            ->assertJsonPath('latest.title', 'Recursion')
            ->assertJsonPath('latest.headline', 'New lesson in ICT-PROG1')
            ->assertJsonPath('latest.url', route('notifications.open', $notification->id));

        $this->get(route('notifications.dropdown'))
            ->assertOk()
            ->assertSee('1 unread')
            ->assertSee('Recursion')
            ->assertDontSee('<html', false); // just the dropdown, not a whole page
    }

    public function test_marking_one_notification_read(): void
    {
        app(StudentNotifier::class)->lessonPosted($this->makeLesson());
        $notification = $this->studentA1->notifications()->first();

        $this->actingAs($this->studentA1)->from(route('notifications.index'))
            ->patch(route('notifications.read', $notification->id))
            ->assertRedirect(route('notifications.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_guests_cannot_see_notifications(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->get(route('notifications.count'))->assertRedirect(route('login'));
    }

    /* ------------------------------------------------------------------ */

    private function makeLesson(string $title = 'Variables'): Lesson
    {
        return Lesson::create([
            'title' => $title, 'content' => 'Lesson body about '.Str::lower($title).'.',
            'subject_id' => $this->subject->id, 'academic_term_id' => $this->subject->academic_term_id,
            'teacher_id' => $this->teacher->id,
        ]);
    }

    /** @param array<Section> $sections */
    private function makeQuiz(array $sections, bool $published = false): Quiz
    {
        $quiz = Quiz::create([
            'title' => 'Quiz 1', 'subject_id' => $this->subject->id, 'academic_term_id' => $this->subject->academic_term_id,
            'teacher_id' => $this->teacher->id, 'passing_score' => 75,
            'is_published' => $published, 'published_at' => $published ? now() : null,
        ]);
        $quiz->questions()->create([
            'question' => 'Which one stores a value?',
            'options_json' => ['A' => 'A loop', 'B' => 'A variable'],
            'correct_answer' => 'B',
        ]);
        $quiz->sections()->sync(collect($sections)->pluck('id'));

        return $quiz;
    }

    /** @return array<\Symfony\Component\Mailer\SentMessage> */
    private function sentMails(): array
    {
        return app('mailer')->getSymfonyTransport()->messages()->all();
    }
}
