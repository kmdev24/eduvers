<?php

namespace Database\Seeders;

use App\Enums\LevelType;
use App\Enums\TeacherType;
use App\Enums\TrackCategory;
use App\Enums\UserRole;
use App\Enums\AnnouncementAudience;
use App\Models\AcademicTerm;
use App\Models\Announcement;
use App\Models\GradeLevel;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TrackStrand;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Baseline data for Movers Institute of Technology and Education.
 * Change the default passwords before going live.
 */
class EduVersSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Academic terms — SHS runs 3 terms per academic year
        $year  = '2026-2027';
        $terms = collect([1 => '1st Term', 2 => '2nd Term', 3 => '3rd Term'])
            ->map(fn ($name, $num) => AcademicTerm::updateOrCreate(
                ['academic_year' => $year, 'term_number' => $num],
                ['name' => $name, 'is_current' => $num === 1],
            ));

        // 2. Grade levels
        $g11   = GradeLevel::updateOrCreate(['name' => 'Grade 11'], ['level_type' => LevelType::Shs]);
        $g12   = GradeLevel::updateOrCreate(['name' => 'Grade 12'], ['level_type' => LevelType::Shs]);
        $yr1   = GradeLevel::updateOrCreate(['name' => '1st Year'], ['level_type' => LevelType::College]);
        GradeLevel::updateOrCreate(['name' => '2nd Year'], ['level_type' => LevelType::College]);

        // 3. Tracks / strands (edit to match the school's actual offerings)
        $ict = TrackStrand::updateOrCreate(['name' => 'ICT'],  ['category' => TrackCategory::TechPro]);
        TrackStrand::updateOrCreate(['name' => 'ABM'],  ['category' => TrackCategory::Academic]);
        TrackStrand::updateOrCreate(['name' => 'HUMSS'], ['category' => TrackCategory::Academic]);
        TrackStrand::updateOrCreate(['name' => 'TVL-HE'], ['category' => TrackCategory::Tvl]);
        $hrs = TrackStrand::updateOrCreate(
            ['name' => 'HRS'],   // Hospitality and Restaurant Services — 2-year bundle course
            ['category' => TrackCategory::College],
        );

        // 4. Sections
        $shsSection = Section::updateOrCreate(
            ['name' => 'ICT 11-A', 'grade_level_id' => $g11->id, 'track_strand_id' => $ict->id],
            ['capacity' => 40],
        );
        $hrsSection = Section::updateOrCreate(
            ['name' => 'HRS 1-A', 'grade_level_id' => $yr1->id, 'track_strand_id' => $hrs->id],
            ['capacity' => 35],
        );

        // 5. Users
        $developer = User::updateOrCreate(['email' => 'developer@eduvers.test'], [
            'name' => 'EduVers Developer', 'password' => 'password',
            'role' => UserRole::Developer,
        ]);

        $teacher = User::updateOrCreate(['email' => 'teacher@eduvers.test'], [
            'name' => 'Sample Teacher', 'password' => 'password',
            'role' => UserRole::Teacher, 'teacher_type' => TeacherType::FullTime,
        ]);

        User::updateOrCreate(['email' => 'student@eduvers.test'], [
            'name' => 'Sample Student', 'password' => 'password',
            'role' => UserRole::Student, 'section_id' => $shsSection->id,
        ]);

        // 6. Sample subjects + teaching load
        $prog = Subject::updateOrCreate(['code' => 'ICT-PROG1'], [
            'name' => 'Computer Programming 1',
            'grade_level_id' => $g11->id, 'track_strand_id' => $ict->id,
            'academic_term_id' => $terms[1]->id,
        ]);
        $fbs = Subject::updateOrCreate(['code' => 'HRS-FBS1'], [
            'name' => 'Food and Beverage Services',
            'grade_level_id' => $yr1->id, 'track_strand_id' => $hrs->id,
            'academic_term_id' => $terms[1]->id,
        ]);

        $prog->assignTeacher($teacher, $shsSection);
        $fbs->assignTeacher($teacher, $hrsSection);

        // 7. Demo content: one lesson and one published quiz for ICT 11-A
        Lesson::firstOrCreate(
            ['title' => 'Welcome to Computer Programming 1', 'subject_id' => $prog->id],
            [
                'academic_term_id' => $prog->academic_term_id,
                'teacher_id'       => $teacher->id,
                'content'          => "# Welcome!\n\nIn this subject you will learn the **fundamentals of programming**.\n\n## What we'll cover\n\n- Variables and data types\n- Conditions and loops\n- Functions\n\n> Tip: practise a little every day.",
            ],
        );

        $quiz = Quiz::firstOrCreate(
            ['title' => 'Quiz 1: Programming Basics', 'subject_id' => $prog->id],
            [
                'description'      => 'Choose the best answer for each question. You can only take this quiz once.',
                'academic_term_id' => $prog->academic_term_id,
                'teacher_id'       => $teacher->id,
                'passing_score'    => 75,
                'reveal_answers'   => true,
                'is_published'     => true,
                'published_at'     => now(),
            ],
        );

        if ($quiz->questions()->doesntExist()) {
            $quiz->questions()->createMany([
                ['question' => 'Which of these is used to store a value in a program?', 'options_json' => ['A' => 'A loop', 'B' => 'A variable', 'C' => 'A comment', 'D' => 'A monitor'], 'correct_answer' => 'B'],
                ['question' => 'What does a loop do?', 'options_json' => ['A' => 'Repeats a set of instructions', 'B' => 'Deletes files', 'C' => 'Stores images'], 'correct_answer' => 'A'],
                ['question' => 'True or false: a function can be reused many times.', 'options_json' => ['A' => 'True', 'B' => 'False'], 'correct_answer' => 'A'],
            ]);
        }

        $quiz->sections()->syncWithoutDetaching([$shsSection->id]);

        // 8. Sample announcements
        Announcement::firstOrCreate(
            ['title' => 'Welcome to EduVers!'],
            [
                'body'      => "Welcome to the new EduVers Learning Management System of Movers Institute of Technology and Education.\n\nTransform, Learn, Lead.",
                'audience'  => AnnouncementAudience::Everyone,
                'author_id' => $developer->id,
                'is_pinned' => true,
            ],
        );

        Announcement::firstOrCreate(
            ['title' => 'Quiz 1 is now open'],
            [
                'body'       => 'Quiz 1: Programming Basics is now available under Quizzes. You can take it once, so review the welcome lesson first.',
                'audience'   => AnnouncementAudience::Section,
                'section_id' => $shsSection->id,
                'author_id'  => $teacher->id,
            ],
        );
    }
}
