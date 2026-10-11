# How EduVers works

A plain-English tour for anyone who has to understand or change this system.
No experience assumed. For the exact technical list, see section 11 of `README.md`.

---

## 1. The big idea: EduVers is a restaurant

A website is just this: **someone asks for something, and something comes back.**

A student clicks "My Subjects". A moment later a page full of their subjects appears.
Between the click and the page, six helpers do one job each — like a restaurant.

| In a restaurant | In EduVers | Where it lives |
|---|---|---|
| The menu — what you may order, and who may order it | **Routes** | `routes/web.php` |
| The doorman — checks you're allowed in | **Middleware** | `app/Http/Middleware/` |
| The waiter — takes the order, fetches it, brings it back | **Controller** | `app/Http/Controllers/` |
| The cook who knows where every ingredient is kept | **Model** | `app/Models/` |
| The pantry where food is stored | **Database** | MySQL (the `eduvers` database) |
| The plate the food is served on | **View** | `resources/views/` |
| The taster who checks nothing is spoiled | **Tests** | `tests/` |

Nobody does two jobs. The waiter never cooks. The plate never decides what's on it.
**That is the single most important rule in this whole system.** When you add something
new, find which helper's job it is and put it there.

---

## 2. How it was built, from an empty folder

Useful if you have to explain or repeat the build.

### The starting point

```
composer create-project laravel/laravel eduvers
```

That one command produces an empty Laravel app: the folders, `artisan`, a working
`.env.example`. Every folder in the map in section 4 came from it. Nothing in `vendor/` or
`node_modules/` is written by us — those are downloaded, which is why they are never
copied into the handover package.

**No starter kit was used.** Laravel offers ready-made login screens (Breeze, Jetstream).
This project does not use them: `app/Http/Controllers/Auth/LoginController.php` and
`PasswordResetController.php` are written by hand. That is why the sign-in page looks like
the school rather than like a Laravel default, and why rules such as the five-attempt
lockout and the 30-minute idle sign-out sit in files we can read and explain.

### The order things were built

The data model had to be built in dependency order — you cannot put a section in a grade
level that does not exist yet. **The migration filenames still show that order**, because
each is stamped with the day it was written:

| Order | File | Why it had to come first |
|---|---|---|
| 1 | `..._create_academic_terms_table` | Everything belongs to a term |
| 2 | `..._create_track_strands_table` | ICT, ABM, HRS … |
| 3 | `..._create_grade_levels_table` | Grade 11, 1st Year … |
| 4 | `..._create_sections_table` | Needs a grade level **and** a strand |
| 5 | `..._create_subjects_table` | Needs a grade level and a term |
| 6 | `..._create_subject_teacher_table` | A class = subject × section |
| 7 | `..._create_lessons_table`, `..._create_quizzes_table` | Both hang off a subject |

See the real list, oldest first:

```bash
ls database/migrations
```

### The features, in the order they shipped

Git recorded every step. This is the project's actual history:

```bash
git log --reverse --format="%ad  %s" --date=short
```

```
2026-09-29  EduVers LMS: initial commit          <- roles, subjects, lessons, quizzes
2026-09-29  Add notifications
2026-09-29  Live notification bell with pop-up
2026-09-29  System check page, clearer notify messages, login fix
2026-09-29  Fully responsive layout and fit-to-screen header clock
2026-10-10  Subject enrollment with approval, password reset, idle sign-out
2026-10-10  Keep class enrollment to the student's own year level
2026-10-10  Rename the developer role to admin, add a show/hide password eye
```

Be honest about one detail if asked: the first commit is a squash. The data model and the
three portals were built over the days before it, which is why migrations dated 09-27 and
09-28 sit inside a commit dated 09-29.

### What each tool is for

Everything below is listed in `composer.json` (PHP side) or `package.json` (browser side).

| Tool | Job |
|---|---|
| **Laravel 13** | The framework: routing, database, templates, sessions |
| **PHP 8.3** | The language the server runs |
| **MySQL** | The database |
| **Tailwind CSS 4** | Styling by writing classes in the page instead of separate CSS files |
| **Vite 8** | Bundles the CSS and JavaScript (`npm run build`) |
| **PHPUnit 12** | Runs the tests (`php artisan test`) |
| **Laravel Tinker** | A console for poking at live data (`php artisan tinker`) |
| **Laravel Pint** | Formats PHP to one consistent style |
| **Laravel Pail** | Reads the log file nicely (`php artisan pail`) |
| **Faker** | Invents fake names and emails for tests |

---

## 3. Setting up Visual Studio Code

### The one-click way — already built

Double-click **handover\Edit-EduVers.bat** (or the "Edit EduVers" Desktop shortcut the
installer creates). It does four things:

1. Opens the project in VS Code
2. Starts `npm run dev` — save a file and the browser updates by itself
3. Starts the site at `http://127.0.0.1:8000`
4. Waits. Press a key in that window when you finish, and it stops everything and runs
   `npm run build`, so presentation mode shows your newest work

For the defense itself use **Start-EduVers.bat** instead — presentation mode, no editor.

### By hand, if you want to know what it is doing

Open **Laragon's Terminal** (not PowerShell — only Laragon's terminal has PHP and MySQL
on its PATH), then:

```bash
cd C:\laragon\www\eduvers
```

```bash
code .
```

Then two more Laragon terminals, one command in each:

```bash
npm run dev
```

```bash
php artisan serve
```

Leave both running while you work. When finished press `Ctrl+C` in both, then run
`npm run build` so the styles are saved for presentation mode.

### Extensions worth installing

In VS Code press `Ctrl+Shift+X` and search for each name:

| Extension | What it gives you |
|---|---|
| **PHP Intelephense** | Autocomplete and jump-to-definition for PHP |
| **Laravel Extra Intellisense** | Autocompletes route names, view names and config keys |
| **Laravel Blade Snippets** | Colours and shortcuts for `.blade.php` files |
| **Tailwind CSS IntelliSense** | Suggests class names and shows the colour |
| **EditorConfig for VS Code** | Keeps indenting consistent with the rest of the project |

These are personal choices, so this project deliberately ships **no** `.vscode` folder —
it is in `.gitignore` so everyone keeps their own settings.

### Commands you will actually use

```bash
php artisan test
```

```bash
php artisan migrate
```

```bash
php artisan tinker
```

`tinker` opens a console against the live data: type `App\Models\User::count()` and press
Enter. To reformat PHP to the project's style, run `vendor\bin\pint`.

### Two habits

**Never edit anything inside `vendor/` or `node_modules/`.** They are downloaded and get
replaced. If something there looks wrong, the fix belongs in your own code.

**Run `php artisan test` before calling anything finished.** It takes about ten seconds
and checks 141 things.

---

## 4. The folders, one line each

```
eduvers/
├── routes/web.php        the menu: every address on the site
├── app/
│   ├── Http/Controllers/ the waiters, one folder per role (Admin, Teacher, Student)
│   ├── Models/           the cooks: one file per kind of thing (User, Subject, Quiz)
│   ├── Http/Middleware/  the doormen (is this person signed in? the right role?)
│   ├── Http/Requests/    the order checkers (is this form filled in properly?)
│   ├── Support/          helpers that don't fit elsewhere (Gradebook, ClassRoster)
│   └── Enums/            short fixed lists (the roles: admin, teacher, student)
├── resources/
│   ├── views/            the plates: the actual pages people see (.blade.php)
│   ├── js/app.js         the small bits of liveliness (popups, live search)
│   └── css/app.css       the colours and spacing
├── database/
│   ├── migrations/       the pantry blueprints: what shelves exist
│   └── seeders/          the starter stock: demo accounts and sample data
├── tests/                the taster
├── public/               the front door — the only folder the internet can see
├── config/               settings
└── handover/             installing and running it (you are reading one of these)
```

---

## 5. Follow one click, all the way

A student clicks **My Subjects**. Here is every file it touches, in order.
Open them side by side — this one journey explains the whole system.

**Step 1 — The browser asks for an address**

```
GET /student/subjects
```

**Step 2 — The menu is checked** — `routes/web.php`, line 146

```php
Route::get('/subjects', [Student\SubjectController::class, 'index'])->name('subjects.index');
```

This says: *when someone asks for `/student/subjects`, send them to the `index` method
of `Student\SubjectController`.* It sits inside a group that adds the `student` prefix
and the rule `role:student`.

**Step 3 — The doormen check** — `app/Http/Middleware/`

Two checks run before the waiter is even called:
- Are you signed in? If not → back to the front page.
- Are you a **student**? A teacher asking for this address gets "403 Forbidden".

**Step 4 — The waiter works** — `app/Http/Controllers/Student/SubjectController.php`

Shortened here to the three lines that matter — the real method also counts each
subject's lessons and quizzes:

```php
public function index(Request $request): View
{
    $student = $request->user();
    $term    = AcademicTerm::getCurrent();

    $classes = $student->studentClasses()->inTerm($term?->id)->get();   // ask the cook

    return view('student.subjects.index', compact('student', 'term', 'classes'));
}
```

The waiter **never touches the pantry directly.** It asks the cook (`$student`) for the
classes, then hands the result to a plate. Notice it is short — that is deliberate.

**Step 5 — The cook fetches** — `app/Models/User.php`

```php
public function studentClasses(): Builder
{
    return SubjectTeacher::query()->where(fn (Builder $q) => $q
        ->where('section_id', $this->section_id ?? 0) // no section yet: matches nothing
        ->orWhereIn('id', ClassEnrollment::approved()->where('student_id', $this->id)->select('subject_teacher_id')));
}
```

This is the rule *"a student's classes are their section's classes, plus any class an
admin approved them into"* — written **once**, in the cook's own file. The student
dashboard, the quizzes page and this page all call the same method. Change the rule here
and it changes everywhere at once. That is why it lives in the model and not in the waiter.

**Step 6 — The plate is filled** — `resources/views/student/subjects/index.blade.php`

```blade
@foreach ($classes as $offering)
    <p>{{ $offering->subject->name }}</p>
    <p>{{ $offering->teacher?->name ?? 'No teacher yet' }}</p>
@endforeach
```

A `.blade.php` file is just HTML with small holes in it. `{{ ... }}` means
"put the value here". `@foreach` means "repeat this once per item". The plate decides
how things **look**, never what they **are**.

**Step 7 — The page arrives.** Finished HTML goes back to the browser.

That's it. Every page in EduVers is this same seven-step trip.

---

## 6. The pantry: migrations and models

These two are easy to confuse. They are completely different jobs.

**A migration builds the shelves.** It runs once, changes the database's *shape*, and is
never edited afterwards. Each one is a dated file in `database/migrations/`:

```
2026_10_10_000005_add_year_level_and_strand_to_users_table.php
```

That name tells you *when* and *what*. Laravel keeps a list of which ones have already
run, in a table called `migrations`, so it never runs the same one twice. Adding a new
column to a real system = write a new migration, never edit an old one. This is why
`php artisan migrate` is needed after copying a database: it builds any shelves the copy
is missing.

**A model knows what's on the shelves.** `app/Models/Subject.php` is how the rest of the
code talks about subjects without writing database language. It also holds the *rules*
about subjects, for example:

```php
/**
 * Sections that can take this subject: same grade level, and same strand
 * unless it is a core subject (no strand).
 */
public function eligibleSections(): Builder
```

Rules live with the thing they describe.

---

## 7. How a developer adds a new feature

Every feature in this system was built in the same six moves. Adding "Year Levels" went
exactly like this:

1. **Shelf** — does the database need a new table or column? Write a migration.
   `database/migrations/`
2. **Cook** — create or extend the model, and put the rules in it. `app/Models/GradeLevel.php`
3. **Menu** — add the addresses. `routes/web.php`
4. **Waiter** — write the controller that fetches and saves. `app/Http/Controllers/Admin/GradeLevelController.php`
5. **Plate** — build the page. `resources/views/admin/levels/index.blade.php`
6. **Taster** — write a test that fails if the feature breaks. `tests/Feature/GradeLevelTest.php`

Then: `php artisan test`. If it's green, the feature is done.

Skipping step 6 is how systems rot. The tests are what let anyone change this code later
without being afraid.

---

## 8. When something breaks, look here first

| What you see | Where the problem almost always is |
|---|---|
| "404 Not Found" | The address isn't in `routes/web.php` |
| "403 Forbidden" | The role guard — you're signed in as the wrong role |
| "500 Server Error" | The controller or model. Read `storage/logs/laravel.log` |
| Page looks plain, no colours | Styles weren't built. Run `npm run build` |
| "Column not found" | A migration hasn't run. Run `php artisan migrate` |
| A button does nothing | `resources/js/app.js`, and rebuild with `npm run build` |

The log file is the single most useful thing in the project:

```
storage/logs/laravel.log
```

The newest error is at the **bottom**.

---

## 9. The four rules that keep this tidy

1. **One job per file.** If a controller gets long, a rule probably belongs in a model.
2. **Write a rule once.** If you copy a condition into a second file, stop — move it into
   the model or into `app/Support/` and call it from both. `ClassRoster::blockedReason()`
   exists because the "can this student join?" rule is needed by the page *and* by the
   save step, and the two must never disagree.
3. **Never edit a migration that has already run.** Write a new one.
4. **A new rule gets a test.** Not for everything — for anything that would quietly give
   the wrong answer.

---

## 10. The words, in plain English

| Word | Means |
|---|---|
| **Laravel** | The toolkit this is built with. It provides the waiters, the menu, the plates. |
| **Route** | One address on the site, and what happens when someone visits it. |
| **Controller** | The code that runs for one address. |
| **Model** | One kind of thing (a User, a Quiz) and its rules. |
| **Blade** | The page format: HTML with `{{ holes }}` in it. |
| **Migration** | A one-time instruction that changes the database's shape. |
| **Seeder** | Fills a fresh database with starter data. |
| **Artisan** | The command tool: `php artisan <something>`. |
| **Composer / npm** | They fetch other people's code that this project uses. |
| **Middleware** | A check that runs before the controller. |
