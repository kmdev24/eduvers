<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Developer;
use App\Http\Controllers\LessonAttachmentController;
use App\Http\Controllers\LessonVideoController;
use App\Http\Controllers\MasterListController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student;
use App\Http\Controllers\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'));

/* Guests only ------------------------------------------------------------ */
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

/* Signed-in users -------------------------------------------------------- */
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Sends each user to their own role's dashboard
    Route::get('/dashboard', fn (Request $request) => redirect()->route(
        $request->user()->role->dashboardRoute()
    ))->name('dashboard');

    // Lesson files (private storage). LessonPolicy@view decides who may download.
    Route::get('/lessons/{lesson}/attachment', LessonAttachmentController::class)->name('lessons.attachment');
    Route::get('/lessons/{lesson}/video', LessonVideoController::class)->name('lessons.video');

    // Profile & password (all roles)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // In-app notifications (bell). Each user only ever sees their own.
    // whereUuid: a malformed id is a 404 (PostgreSQL would otherwise error on the uuid column).
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/count', [NotificationController::class, 'count'])->name('notifications.count');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->whereUuid('notification')->name('notifications.open');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->whereUuid('notification')->name('notifications.read');

    /* Developer + Teacher ----------------------------------------------- */
    Route::middleware('role:developer,teacher')->group(function () {
        Route::resource('announcements', AnnouncementController::class)->except(['show', 'create']);
        Route::get('/sections/{section}/master-list', MasterListController::class)->name('sections.masterlist');
    });

    /* Developer (Admin) ------------------------------------------------- */
    Route::middleware('role:developer')
        ->prefix('developer')->name('developer.')
        ->group(function () {
            Route::get('/dashboard', Developer\DashboardController::class)->name('dashboard');

            Route::resource('users', Developer\UserController::class)->except('show');
            Route::resource('sections', Developer\SectionController::class)->except('show');
            Route::resource('subjects', Developer\SubjectController::class)->except('show');

            Route::get('/subjects/{subject}/teachers', [Developer\SubjectTeacherController::class, 'edit'])->name('subjects.teachers.edit');
            Route::put('/subjects/{subject}/teachers', [Developer\SubjectTeacherController::class, 'update'])->name('subjects.teachers.update');

            Route::post('/terms/year', [Developer\AcademicTermController::class, 'storeYear'])->name('terms.year');
            Route::patch('/terms/{term}/current', [Developer\AcademicTermController::class, 'makeCurrent'])->name('terms.current');
            Route::resource('terms', Developer\AcademicTermController::class)->except(['show', 'create']);

            Route::resource('tracks', Developer\TrackStrandController::class)
                ->parameters(['tracks' => 'strand'])
                ->except(['show', 'create']);
        });

    /* Teacher ----------------------------------------------------------- */
    Route::middleware('role:teacher')
        ->prefix('teacher')->name('teacher.')
        ->group(function () {
            Route::get('/dashboard', Teacher\DashboardController::class)->name('dashboard');

            Route::resource('lessons', Teacher\LessonController::class);

            Route::resource('quizzes', Teacher\QuizController::class)->except('show');
            Route::patch('/quizzes/{quiz}/publish', [Teacher\QuizController::class, 'publish'])->name('quizzes.publish');

            Route::scopeBindings()->group(function () {
                Route::post('/quizzes/{quiz}/questions', [Teacher\QuizQuestionController::class, 'store'])->name('quizzes.questions.store');
                Route::get('/quizzes/{quiz}/questions/{question}/edit', [Teacher\QuizQuestionController::class, 'edit'])->name('quizzes.questions.edit');
                Route::put('/quizzes/{quiz}/questions/{question}', [Teacher\QuizQuestionController::class, 'update'])->name('quizzes.questions.update');
                Route::delete('/quizzes/{quiz}/questions/{question}', [Teacher\QuizQuestionController::class, 'destroy'])->name('quizzes.questions.destroy');
            });

            Route::get('/gradebook', [Teacher\GradebookController::class, 'index'])->name('gradebook');
            Route::get('/gradebook/export', [Teacher\GradebookController::class, 'export'])->name('gradebook.export');
            Route::get('/gradebook/print', [Teacher\GradebookController::class, 'print'])->name('gradebook.print');
        });

    /* Student ----------------------------------------------------------- */
    Route::middleware('role:student')
        ->prefix('student')->name('student.')
        ->group(function () {
            Route::get('/dashboard', Student\DashboardController::class)->name('dashboard');

            Route::get('/subjects', [Student\SubjectController::class, 'index'])->name('subjects.index');
            Route::get('/subjects/{subject}', [Student\SubjectController::class, 'show'])->name('subjects.show');
            Route::get('/lessons/{lesson}', [Student\LessonController::class, 'show'])->name('lessons.show');

            Route::get('/quizzes', [Student\QuizController::class, 'index'])->name('quizzes.index');
            Route::get('/quizzes/{quiz}', [Student\QuizController::class, 'show'])->name('quizzes.show');
            Route::post('/quizzes/{quiz}', [Student\QuizController::class, 'submit'])->name('quizzes.submit');

            Route::get('/grades', Student\GradeController::class)->name('grades');

            Route::get('/announcements/{announcement}', [Student\AnnouncementController::class, 'show'])->name('announcements.show');
        });
});
