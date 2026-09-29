# EduVers — Learning Management System

**Movers Institute of Technology and Education**
*Transform, Learn, Lead*

EduVers is the web-based Learning Management System (LMS) of Movers Institute of Technology and Education. It serves **Senior High School** (Grade 11–12, three terms per academic year) and the **College 2-year bundle course in Hospitality and Restaurant Services (HRS)**, with separate portals for administrators, teachers and students.

---

## Contents

1. [Project overview](#1-project-overview)
2. [Tech stack](#2-tech-stack)
3. [Local setup (Laragon + MySQL)](#3-local-setup-laragon--mysql)
4. [Demo credentials](#4-demo-credentials)
5. [Running the automated tests](#5-running-the-automated-tests)
6. [Feature checklist](#6-feature-checklist)
7. [Known scope boundaries](#7-known-scope-boundaries)
8. [Production deployment](#8-production-deployment)
9. [Backups and restore](#9-backups-and-restore)
10. [Troubleshooting](#10-troubleshooting)
11. [Project structure](#11-project-structure)

---

## 1. Project overview

| Role | Who | What they do |
|---|---|---|
| **Developer** (Admin) | IT / Registrar staff | Manage users, sections, subjects, teacher assignments, academic terms, tracks & strands, and school-wide announcements |
| **Teacher** | Full-time, Part-time or Movers faculty | Post lessons with attachments, build and publish quizzes, view the gradebook, export grades, post section announcements |
| **Student** | SHS and College learners | Read lessons, download materials, take quizzes (scored instantly), view grades and announcements |

**Academic structure**

- **Senior High School:** Grade 11 and Grade 12, 3 terms per academic year, with Academic, TechPro and TVL strands.
- **College:** HRS (Hospitality and Restaurant Services), a 2-year bundle course (1st Year and 2nd Year).
- **Teacher types:** Full-time, Part-time, Movers.

---

## 2. Tech stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 (PHP 8.3+) |
| Database | MySQL 8 (Laragon default) |
| Front end | Blade templates, Tailwind CSS v4 (via Vite), a little vanilla JavaScript |
| File storage | Laravel Storage, **private** `local` disk (`storage/app/private`) |
| Tests | PHPUnit 12 (feature tests on an in-memory SQLite database) |

Design theme: warm white and slate-dark surfaces with gold accents (`gold-500 #D4AF37`, `gold-600 #AA7C11`, champagne `#C5A059`). The palette lives in `tailwind.config.js`; shared component styles are in `resources/css/app.css`.

---

## 3. Local setup (Laragon + MySQL)

### Requirements

- [Laragon](https://laragon.org/) with **PHP 8.3+**, **MySQL 8**, **Composer** and **Node.js 20+**.
- The project folder at `C:\laragon\www\eduvers`.

### Steps

Run these in Laragon's terminal (**Menu → Terminal**), inside the project folder.

```bash
# 1. PHP and JavaScript dependencies
composer install
npm install

# 2. Environment file (skip if .env already exists)
copy .env.example .env
php artisan key:generate
```

**3. Create the database.** Open **HeidiSQL** from Laragon (Menu → MySQL → HeidiSQL), connect as `root` with no password, and create a database named `eduvers` with collation `utf8mb4_unicode_ci`. Or from the terminal:

```bash
mysql -u root -e "CREATE DATABASE eduvers CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

**4. Check the database settings in `.env`.** These are Laragon's defaults:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=eduvers
DB_USERNAME=root
DB_PASSWORD=
```

```bash
# 5. Create the tables and load the demo data
php artisan migrate --seed

# 6. Start the front-end build (keep this window open)
npm run dev
```

**7. Open the site**, using either option:

- **Laragon virtual host:** click **Stop** then **Start All** in Laragon, and open <http://eduvers.test>. If you see the Laragon welcome page instead, enable *Preferences → General → Auto virtual hosts* and restart.
- **Built-in server:** in a second terminal run `php artisan serve`, then open <http://localhost:8000>.

> **Moving from the earlier SQLite setup:** data is not copied from `database/database.sqlite`. After switching `.env` to MySQL, run `php artisan migrate --seed` on the new, empty `eduvers` database and re-create any records you entered.

> **Logo:** the school logo is read from `public/images/logo.jpg`. If the file is missing, a gold "EV" badge is shown instead.

---

## 4. Demo credentials

Created by `php artisan db:seed` (or `migrate --seed`). **All demo passwords are `password`.**

| Role | Email | Notes |
|---|---|---|
| Developer (Admin) | `developer@eduvers.test` | Full access |
| Teacher | `teacher@eduvers.test` | Full-time; teaches ICT 11-A (Computer Programming 1) and HRS 1-A (Food and Beverage Services) |
| Student | `student@eduvers.test` | Enrolled in ICT 11-A |

The seeder also creates:

- Terms 1–3 for 2026-2027
- Grade 11–12 and 1st–2nd Year
- ICT, ABM, HUMSS, TVL-HE and HRS strands
- Two sections and two subjects
- A sample lesson and a published 3-question quiz
- Two announcements

> ⚠️ Running the seeder again **resets these three accounts' passwords to `password`**. Never run the demo seeder on the live server. See [Production deployment](#8-production-deployment).

---

## 5. Running the automated tests

```bash
php artisan test
```

Tests run on a **fresh in-memory SQLite database** (configured in `phpunit.xml`), so they never touch your MySQL data. Laragon's PHP includes the SQLite driver.

| File | What it checks |
|---|---|
| `tests/Feature/RoleAccessTest.php` | Every Developer, Teacher and Student page. Guests are sent to login, the owning role gets **200**, the other roles get **403 Forbidden**. Also: `/dashboard` redirects to the right portal; announcements are closed to students; teachers can't edit another teacher's lesson or quiz; students can only take published quizzes for their own section; a quiz is scored immediately and only once; master lists and gradebook exports are restricted. |
| `tests/Feature/AuthenticationTest.php` | Login, wrong password, lockout after 5 failed attempts, logout, signed-in users kept off the login page, password change with the current password. |
| `tests/Feature/NotificationTest.php` | Who is notified for lessons, quizzes and announcements (and who isn't); no repeat alerts on edit/re-publish; bell entry is instant while emails wait for the queue worker, then send; nothing is sent when the transaction rolls back; email content, theme and link-injection safety; the Brevo mailer; the bell, Notifications page, mark as read, and that users can't touch each other's notifications. |
| `tests/Feature/ExampleTest.php` | The home page redirects guests to the login page. |

> If tests fail with a *Vite manifest not found* or font-manifest error, run `npm run build` once (or keep `npm run dev` running) and try again.

---

## 6. Feature checklist

### Authentication and security
- [x] Gold-themed login page with school logo and motto
- [x] Sign-in lockout after 5 failed attempts (per email + IP)
- [x] Role-based redirect to each portal; role guard middleware (`role:developer`, `role:teacher`, `role:student`)
- [x] Ownership policies: teachers edit only their own lessons, quizzes and announcements
- [x] Students only see content for their own section
- [x] Lesson files on private storage, downloadable only by authorized users
- [x] Themed 403 "Access denied" page
- [x] Profile page for all users: change name; change password (requires current password; min. 8 characters with letters and numbers)

### Developer (Admin)
- [x] Dashboard: institutional banner, totals, section fill levels, faculty by teacher type, current term, recent accounts
- [x] **Users:** create, edit and delete; filters by role and teacher type; search; initial password with generator; student section assignment with capacity check; can't delete or demote self or the last developer
- [x] **Sections:** SHS and College sections with strand and capacity; strand–level compatibility check; capacity can't go below enrolment
- [x] **Subjects:** core or strand-specific, per grade level and term
- [x] **Teacher assignment:** one teacher per subject per section, with a coverage indicator
- [x] **Academic Terms:** create a full year (1st–3rd Term) in one step, add single terms, edit, delete, set the current term
- [x] **Tracks & Strands:** Academic, TechPro, TVL and College categories; add, edit and delete strands
- [x] **Announcements:** school-wide, all teachers, all students or one section; pin; expiry date
- [x] **Master list:** printable view and CSV for any section

### Teacher
- [x] Dashboard: stats, class cards with Grades and Master list shortcuts, latest submissions, announcements
- [x] **Lessons:** Markdown content, one attachment each (PDF, Office, images, ZIP; 20 MB max)
- [x] **Video lessons:** upload an MP4 / WebM / MOV (200 MB max, streamed privately with seeking) **or** paste a YouTube, Vimeo or Google Drive link (embedded player)
- [x] **Quizzes:** multiple choice (A–D), pass mark, choose target sections, publish or unpublish, optional answer reveal, questions lock after the first submission
- [x] **Gradebook:** per term and class: student × quiz grid, averages, pass rate, completion
- [x] **Exports:** Grades CSV, Submissions CSV, printable class record (browser *Save as PDF*)
- [x] **Announcements** to the sections they teach
- [x] **Master list** for their own sections

### Student
- [x] Dashboard: section banner, stats, announcements, subjects, pending quizzes, new lessons, recent scores
- [x] **My Subjects** → lessons and quizzes per subject
- [x] **Lesson reader:** video player at the top (HTML5 or embedded), then content; open PDFs in the browser, download attachments, previous/next navigation
- [x] **Quizzes:** one attempt, instant automated scoring, score ring, answer review (if the teacher allows)
- [x] **My Grades:** results by term and subject with averages
- [x] **Notifications:** header bell with unread badge and dropdown, full Notifications page (All / Unread, mark one or all as read); clicking a notification opens the lesson, quiz or announcement
- [x] **Email alerts** for new lessons, published quizzes and announcements (queued, EduVers-branded)

### Branding and reports
- [x] Movers Institute logo on the login page, sidebar, phone-size top bar and printed report letterheads
- [x] Motto "Transform, Learn, Lead" on the login page, Developer portal and report letterheads
- [x] A4 printable reports with letterhead and signature line

---

## 7. Known scope boundaries

These are deliberate limits of the current phase. Anything below that the school needs is a candidate for a future phase.

| Area | Current behaviour | Not included |
|---|---|---|
| Exports | **CSV** files (UTF-8, open directly in Excel) | Native `.xlsx` workbooks |
| PDF | **Browser-native** printable pages. Use *Print → Save as PDF* | Server-generated PDF files |
| Quizzes | **Multiple choice** (2–4 options), one attempt per student, no time limit | Essay, identification, true/false types (true/false can be written as A/B), timers, retakes, question banks |
| Passwords | Users change their own password on the Profile page; the admin resets forgotten passwords from **Users** | "Forgot password" email links |
| Notifications | Announcements on dashboards | Email or SMS notifications |
| Coursework | Lessons and quizzes | Assignment/homework uploads by students, attendance |
| Accounts | Created one by one by the admin | Bulk CSV import |
| Appearance | Light theme | Dark-mode toggle |

---

## 8. Production deployment

### 8.1 Server requirements

- PHP 8.3+ with the `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `bcmath` and `curl` extensions
- MySQL 8 (or MariaDB 10.6+), Composer, Node.js 20+ (only to build assets)
- A web server (Apache or Nginx) whose document root points to **`public/`**, never the project root
- HTTPS certificate (e.g. Let's Encrypt)

### 8.2 Environment (`.env` on the server)

```dotenv
APP_NAME=EduVers
APP_ENV=production
APP_DEBUG=false            # never true in production: it exposes passwords and code in error pages
APP_URL=https://eduvers.movers.edu.ph   # your real domain

LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=eduvers
DB_USERNAME=eduvers_app     # dedicated user, not root
DB_PASSWORD=<strong password>

SESSION_SECURE_COOKIE=true  # cookies only over HTTPS
```

Create a dedicated database user instead of `root`:

```sql
CREATE DATABASE eduvers CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'eduvers_app'@'localhost' IDENTIFIED BY '<strong password>';
GRANT ALL PRIVILEGES ON eduvers.* TO 'eduvers_app'@'localhost';
FLUSH PRIVILEGES;
```

### 8.3 Deploy commands

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate          # first install only; never regenerate on a live site
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

After each update, pull the new code and run the same commands (without `key:generate`). If you change `.env` later, run `php artisan config:cache` again.

### 8.4 First administrator (no demo data)

Don't run the demo seeder in production. Create the first real admin with Tinker:

```bash
php artisan tinker
>>> App\Models\User::create(['name' => 'Registrar', 'email' => 'registrar@movers.edu.ph', 'password' => 'ChangeMe123', 'role' => 'developer']);
```

Sign in, change the password on **My Profile**, then create terms, strands, sections, subjects and accounts from the Developer portal.

### 8.5 Permissions and uploads

- The web server user must be able to write to `storage/` and `bootstrap/cache/`.
- Lesson files are stored in `storage/app/private/lessons`. **Include this folder in backups.**
- Uploaded lesson videos are stored in `storage/app/private/lessons/{subject}/videos`.
- For 200 MB videos (plus a 20 MB attachment), set these in `php.ini`, then restart PHP / the web server:
  ```ini
  upload_max_filesize = 210M
  post_max_size = 256M
  max_execution_time = 300
  max_input_time = 300
  memory_limit = 256M
  ```
  On Nginx also set `client_max_body_size 256M;`. On Laragon: **Menu → PHP → php.ini**, edit, then **Stop / Start All**.

### 8.6 Go-live checklist

- [ ] `APP_ENV=production` and `APP_DEBUG=false`
- [ ] HTTPS enabled; `APP_URL` uses `https://`; `SESSION_SECURE_COOKIE=true`
- [ ] Dedicated MySQL user (not `root`) with a strong password
- [ ] Demo accounts **not** present (demo seeder not run)
- [ ] `config:cache`, `route:cache`, `view:cache` run
- [ ] Document root is `public/`
- [ ] Nightly database and `storage/app/private` backups scheduled and a restore tested
- [ ] `php artisan test` passes on the release build

---

### 8.7 Deploying to Render.com (Docker)

The repository includes everything Render needs:

| File | Purpose |
|---|---|
| `Dockerfile` | 3-stage build: Composer (PHP deps) → Node 22 (`npm run build`) → **PHP 8.3 + Apache** runtime with `pdo_mysql`, `pdo_pgsql`, `intl`, OPcache |
| `docker/entrypoint.sh` | Runs on every container start: prepares `storage/`, checks `APP_KEY`, runs `config:cache`, `route:cache`, `view:cache`, `event:cache`, then `migrate --force` (retries while the DB wakes up), optionally seeds demo data, fixes permissions, starts Apache |
| `docker/apache/*.conf` | Apache listens on Render's `$PORT` and serves `public/` |
| `docker/php/eduvers.ini` | Upload limits (200 MB videos), OPcache, `Asia/Manila` timezone |
| `render.yaml` | Render Blueprint: web service + managed **PostgreSQL** + persistent disk for uploads |
| `docker-compose.yml` | Run the same image locally with MySQL (`docker compose up --build` → <http://localhost:8080>) |
| `.dockerignore` | Keeps `.env`, `vendor`, `node_modules`, uploads, SQL dumps out of the image |

**Steps**

1. Push the project to GitHub (see 8.8).
2. Generate an app key locally and copy the output:
   ```bash
   php artisan key:generate --show
   ```
3. In Render: **New → Blueprint**, connect the GitHub repo, and apply `render.yaml`. This creates:
   - `eduvers`: Docker web service (Singapore region), health check `/up`, 5 GB disk at `storage/app`
   - `eduvers-db`: PostgreSQL database, linked through `DB_URL`
4. Open the **eduvers** service → **Environment** and set:
   - `APP_KEY` = the value from step 2
   - `APP_URL` = your service URL, e.g. `https://eduvers.onrender.com`
5. Deploy. The first start runs all migrations automatically.
6. Create the first administrator: **Shell** tab of the service →
   ```bash
   php artisan tinker
   >>> App\Models\User::create(['name' => 'Registrar', 'email' => 'registrar@movers.edu.ph', 'password' => 'ChangeMe123', 'role' => 'developer']);
   ```
   *(For a demo site only, you can instead set `SEED_DEMO_DATA=true` for one deploy, then set it back to `false`.)*

**Good to know**

- **Uploads need the disk.** Lesson attachments and uploaded videos are saved in `storage/app/private`. Render's filesystem is otherwise wiped on every deploy. The Blueprint attaches a persistent disk, which requires a paid instance type (Starter or higher) and means one instance only. On the Free plan, uploads disappear on redeploy, so use video **links** (YouTube/Vimeo/Drive) instead.
- **Database:** the app runs on PostgreSQL (Render) as well as MySQL (Laragon/local). To use an external MySQL instead, set `DB_CONNECTION=mysql` and `DB_URL=mysql://user:pass@host:3306/eduvers` (see `.env.example`).
- **HTTPS:** Render terminates TLS; Laravel trusts the proxy (`bootstrap/app.php`), so links and secure cookies use `https://`.
- **Logs:** `LOG_CHANNEL=stderr` sends Laravel logs to Render's **Logs** tab.
- **Free database** instances expire after 30 days. Use a paid plan for real school data and enable Render's backups.

### 8.8 GitHub

The `.gitignore` excludes `.env` and other `.env.*` files, `vendor/`, `node_modules/`, `public/build`, SQLite files, **SQL dumps** (`*.sql`), backups and uploaded lesson files. Only `.env.example` is committed.

```bash
cd C:\laragon\www\eduvers
git init
git add .
git status                    # check: no .env, vendor/, node_modules/, *.sql
git commit -m "EduVers LMS: initial commit"
git branch -M main
git remote add origin https://github.com/<your-account>/eduvers.git
git push -u origin main
```

> Create the empty `eduvers` repository on GitHub first (without a README). Keep it **private**: it's the school's system.

### 8.9 Deploying to Railway (website) + TiDB Cloud (MySQL)

Railway runs the same `Dockerfile`; TiDB Cloud provides a MySQL-compatible database. Pick the **Singapore** region on both for the lowest latency from the Philippines.

**A. TiDB Cloud: create the database**

1. Sign in at [tidbcloud.com](https://tidbcloud.com) and create a **Starter** cluster in **AWS Singapore (ap-southeast-1)**.
2. Open the cluster → **Connect**. Note the **host**, **port (4000)**, **username** (it includes a prefix, e.g. `2aBcDeF.root`) and generate a **password**.
3. Open **SQL Editor** and run:
   ```sql
   CREATE DATABASE eduvers;
   ```
4. Leave the public endpoint enabled. TiDB Cloud only accepts **TLS** connections, which EduVers uses via `MYSQL_ATTR_SSL_CA`.

**B. Railway: deploy the website**

1. Push the project to GitHub (8.8).
2. Railway → **New Project → Deploy from GitHub repo** → choose the repo. Railway detects the `Dockerfile` and reads `railway.toml` (health check `/up`, restart on failure).
3. Service → **Settings**: set the region to **Southeast Asia (Singapore)**.
4. Service → **Variables → Raw Editor**: paste `deploy/railway.env.example`, then replace the `<…>` placeholders:
   - `APP_KEY`: run `php artisan key:generate --show` locally and paste the result
   - `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`: from TiDB's **Connect** dialog
5. Service → **Settings → Networking → Generate Domain** (target port **8080**). `APP_URL` picks it up through `${{RAILWAY_PUBLIC_DOMAIN}}`.
6. **Uploads:** right-click the service → **Attach Volume**, mount path **`/var/www/html/storage/app`**. Without a volume, lesson attachments and uploaded videos are deleted on every redeploy.
7. Deploy. On start, the container caches config, runs `migrate --force` against TiDB, then starts Apache. Watch **Deployments → Logs** for `▶ EduVers: starting web server`.
8. Create the first administrator: service → **⋯ → Shell** (or `railway ssh`), then:
   ```bash
   php artisan tinker
   >>> App\Models\User::create(['name' => 'Registrar', 'email' => 'registrar@movers.edu.ph', 'password' => 'ChangeMe123', 'role' => 'developer']);
   ```
   For a demo only: set `SEED_DEMO_DATA=true`, redeploy once, then set it back to `false`.

**Optional: copy your local Laragon data into TiDB** (instead of starting fresh)

```bash
# 1. Export from Laragon (Laragon terminal)
mysqldump -u root --single-transaction --no-tablespaces eduvers > eduvers-export.sql

# 2. Import into TiDB (HOST, PREFIX and password from TiDB's Connect dialog)
mysql -h HOST -P 4000 -u "PREFIX.root" -p --ssl-mode=REQUIRED eduvers < eduvers-export.sql
```

Then deploy on Railway. Migrations already applied are skipped. Uploaded files in `storage\app\private` are **not** in the dump; re-upload them, or copy them into the Railway volume.

**Troubleshooting**

| Symptom in Railway logs | Fix |
|---|---|
| `SSL connection is required` / `Connections using insecure transport are prohibited` | `MYSQL_ATTR_SSL_CA` is missing: set it to `/etc/ssl/certs/ca-certificates.crt` |
| `Access denied for user` | Use the full username **with prefix** (e.g. `2aBcDeF.root`) and the TiDB password |
| `Unknown database 'eduvers'` | Run `CREATE DATABASE eduvers;` in TiDB's SQL Editor |
| `APP_KEY is not set` | Add `APP_KEY` (from `php artisan key:generate --show`) to Railway variables |
| Health check fails / "Application failed to respond" | Make sure `PORT=8080` and the domain's target port is `8080` |
| Uploaded files disappear after deploy | Attach a Railway volume at `/var/www/html/storage/app` |

### 8.10 Notifications and email alerts

**What triggers them**

| Teacher / developer action | Who is notified |
|---|---|
| Posts a lesson | Students whose section takes that subject |
| Publishes a quiz (first time only) | Students in the quiz's sections. Sections added later to a live quiz are notified when added |
| Posts an announcement | *Section*: that section. *All students* / *School-wide*: every student. *All teachers*: nobody |

Each student gets an **in-app notification** (the bell) and an **email**. Editing a lesson or re-publishing a quiz does not notify anyone again.

**How it works**

- `App\Support\StudentNotifier` finds the recipients; `App\Notifications\NewLessonNotification`, `NewQuizNotification` and `CourseAnnouncementNotification` use Laravel's `database` and `mail` channels.
- The **bell** entry is written during the request (instant, no worker needed). The **email** is a queued job (`ShouldQueue`), so posting to a large class stays fast.
- Both wait until the database transaction commits (`ShouldQueueAfterCommit`): a failed save never sends an alert.
- If mail or the queue is broken, the lesson/quiz/announcement is still saved; the error goes to the log.
- Emails use the EduVers theme (`resources/views/vendor/mail/html/themes/eduvers.css`, template `resources/views/mail/student-activity.blade.php`).

**Local development (Laragon)**

1. `php artisan migrate` (creates the `notifications` table).
2. In `.env` pick a mailer. **Mailtrap** (fake inbox, nothing reaches real students) or **Gmail** with an App Password. Both are in `.env.example`. Leave `MAIL_MAILER=log` to write emails into `storage/logs/laravel.log`.
3. Keep a queue worker open in a second terminal while you test:
   ```bash
   php artisan queue:work
   ```
   Without it, the bell still works but emails wait in the `jobs` table. (`QUEUE_CONNECTION=sync` sends during the request instead.)

**Railway**

Railway's Free, Trial and Hobby plans **block SMTP**, so Gmail/Mailtrap SMTP won't connect from there. EduVers includes a `brevo` mailer that sends over HTTPS instead:

1. Create a free [Brevo](https://www.brevo.com) account (300 emails/day). Under **Senders**, verify the address you'll send from (a Gmail address works).
2. **SMTP & API → API keys → Generate** a key.
3. Railway variables:
   ```
   QUEUE_CONNECTION=database
   MAIL_MAILER=brevo
   BREVO_API_KEY=xkeysib-...
   MAIL_FROM_ADDRESS=your.verified.sender@gmail.com
   MAIL_FROM_NAME=EduVers
   ```
4. Redeploy. The container starts a queue worker next to Apache (`RUN_QUEUE_WORKER=true`, the default). The logs show `▶ EduVers: starting queue worker`.

Settings: `NOTIFICATIONS_MAIL=false` keeps the bell but turns emails off. `RUN_QUEUE_WORKER=false` if you run a separate worker service.

**Troubleshooting**

| Symptom | Fix |
|---|---|
| Bell works, no emails, rows piling up in `jobs` | No worker: run `php artisan queue:work` (local) or check `QUEUE_CONNECTION=database` and the worker line in the Railway logs |
| Rows in `failed_jobs` | `php artisan queue:failed` shows the error; fix the mail settings, then `php artisan queue:retry all` |
| `Brevo API rejected the email (HTTP 401)` | Wrong `BREVO_API_KEY` |
| `Brevo API rejected the email (HTTP 400)` mentioning the sender | `MAIL_FROM_ADDRESS` isn't a verified Brevo sender |
| `Connection could not be established` / timeout on Railway | SMTP is blocked on your plan: use `MAIL_MAILER=brevo` |
| Emails land in spam | Normal for a free Gmail sender; ask students to mark EduVers as "Not spam", or verify a school domain in Brevo |

## 9. Backups and restore

### Database

```bash
# Backup (Laragon terminal or server shell)
mysqldump -u root --single-transaction --routines --triggers eduvers > backups/eduvers-YYYY-MM-DD.sql

# Production (dedicated user; you'll be prompted for the password)
mysqldump -u eduvers_app -p --single-transaction eduvers | gzip > /backups/eduvers-$(date +%F).sql.gz

# Restore into an empty database
mysql -u root eduvers < backups/eduvers-YYYY-MM-DD.sql
gunzip < /backups/eduvers-2026-10-01.sql.gz | mysql -u eduvers_app -p eduvers
```

On Windows without Laragon's terminal, `mysqldump.exe` is in `C:\laragon\bin\mysql\mysql-<version>\bin\`.

### Uploaded lesson files

Back up `storage/app/private/` together with the database dump. Restoring one without the other leaves broken download links.

### Suggested schedule (Linux cron)

```cron
# 1:30 AM daily: database dump, keep 30 days
30 1 * * * mysqldump -u eduvers_app -p'<password>' --single-transaction eduvers | gzip > /backups/eduvers-$(date +\%F).sql.gz && find /backups -name "eduvers-*.sql.gz" -mtime +30 -delete
# 2:00 AM daily: lesson files
0 2 * * * tar -czf /backups/eduvers-files-$(date +\%F).tar.gz -C /var/www/eduvers storage/app/private
```

On Windows servers, use **Task Scheduler** with the same commands in a `.bat` file.

---

## 10. Troubleshooting

| Problem | Fix |
|---|---|
| `SQLSTATE… no column named …` / `table … doesn't exist` | Pending migrations: run `php artisan migrate` |
| `SQLSTATE[HY000] [1049] Unknown database 'eduvers'` | Create the database (see [Local setup](#3-local-setup-laragon--mysql) step 3) |
| `SQLSTATE[HY000] [2002] Connection refused` | Start MySQL in Laragon (**Start All**) |
| `eduvers.test` shows the Laragon welcome page | Laragon **Stop → Start All**; enable *Auto virtual hosts* |
| Styles missing / *Vite manifest not found* | Run `npm run dev` (development) or `npm run build` (production) |
| Large uploads fail silently / *413 Content Too Large* | Raise `upload_max_filesize` and `post_max_size` in `php.ini` (see 8.5) and restart Laragon |
| Uploaded video won't play | Use MP4 (H.264). MOV and some WebM files only play in certain browsers |
| Google Drive video shows "access denied" | In Drive, set sharing to **Anyone with the link** |
| Changes to `.env` not taking effect | `php artisan config:clear` (or `config:cache` in production) |
| 403 page when opening a link | The signed-in role isn't allowed there. This is the role guard working as intended |
| Students see the bell notification but get no email | Start `php artisan queue:work` (see [8.10](#810-notifications-and-email-alerts)) |

---

## 11. Project structure

```
app/
  Enums/                 UserRole, TeacherType, TrackCategory, LevelType, AnnouncementAudience
  Http/Controllers/
    Auth/                LoginController
    Developer/           Dashboard, Users, Sections, Subjects, SubjectTeacher, AcademicTerms, TrackStrands
    Teacher/             Dashboard, Lessons, Quizzes, QuizQuestions, Gradebook (+ export/print)
    Student/             Dashboard, Subjects, Lessons, Quizzes, Grades, Announcements
    AnnouncementController, MasterListController, ProfileController, LessonAttachmentController,
    NotificationController (bell, Notifications page, mark as read)
  Http/Middleware/       EnsureUserHasRole (the "role:" route guard)
  Http/Requests/         Form validation (users, sections, subjects, lessons)
  Models/                User, AcademicTerm, TrackStrand, GradeLevel, Section, Subject, SubjectTeacher,
                         Lesson, Quiz, QuizQuestion, QuizSubmission, Announcement
  Mail/Transport/        BrevoApiTransport (email over HTTPS for hosts that block SMTP)
  Notifications/         NewLessonNotification, NewQuizNotification, CourseAnnouncementNotification
  Policies/              LessonPolicy, QuizPolicy, AnnouncementPolicy
  Support/               Gradebook (shared grade calculations), Csv (Excel-friendly CSV export),
                         StudentNotifier (who gets notified)
database/
  migrations/            All tables (MySQL/TiDB, PostgreSQL and SQLite)
  seeders/               EduVersSeeder (demo data)
resources/
  css/app.css            Tailwind + EduVers component classes
  js/app.js              Sidebar, confirmations, form helpers
  views/                 Blade views per role, components/, reports/ (printables),
                         mail/ + vendor/mail/ (EduVers email template and theme)
routes/web.php           All routes, grouped by role
tests/Feature/           Role guard, authentication and notification tests
public/images/logo.jpg   Movers Institute logo
```

---

© Movers Institute of Technology and Education · EduVers Learning Management System
