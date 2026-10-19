# How to Start a Laravel Project (Even if You've Never Done It)

This guide shows how to make a simple home page with Laravel using Laragon, step by step. Just follow the pictures.

---

## What You Need
1. Windows PC (10 or 11)
2. Laragon (a program that runs PHP and MySQL for you)
3. 15 minutes

---

## Step 1: Install Laragon
1. Go to [laragon.org/download](https://laragon.org/download/)
2. Click **Download**
3. Open the file you downloaded
4. Keep clicking **Next**, **Next**, **Next** (don't change anything)
5. When finished, a black window appears. Click **Start All**

![Laragon after install](handover/help/laragon-start.png)

You now have PHP and MySQL running.

---

## Step 2: Create the Project
1. On Laragon, click **Terminal** (top-left)
2. A black window opens. Type this **exactly** and press Enter:

```bash
composer create-project laravel/laravel my-first-project
```

Wait about 2 minutes. It says "Application ready!" when done.

---

## Step 3: Open It in Browser
1. On Laragon, find **my-first-project** in the list
2. Right-click → **Quick add → www**
3. Click **Start All** again
4. Open your web browser
5. Go to [http://my-first-project.test](http://my-first-project.test)

You see the Laravel welcome page!

---

## Step 4: Make Your Own Home Page
1. Go to Laragon → Terminal again
2. Type:

```bash
cd my-first-project
```

3. Now open the code: Laragon → **my-first-project** → Right-click → **Code** (opens VS Code)

4. In VS Code, find `resources/views/welcome.blade.php` on the left
5. Replace everything in that file with:

```html
<!DOCTYPE html>
<html>
<head>
    <title>My First Laravel Page</title>
</head>
<body style="text-align: center; margin-top: 100px;">
    <h1>Hello World!</h1>
    <p>I just made my first Laravel page.</p>
    <p>Date: {{ date('Y-m-d') }}</p>
</body>
</html>
```

6. Save the file (Ctrl+S)
7. Refresh your browser [http://my-first-project.test](http://my-first-project.test)

You see your custom page!

---

## Step 5: Add a Database (Optional)
1. Go to Laragon → **MySQL** (top-right)
2. Username: `root` (no password)
3. Click **Create Database**
4. Name: `my_database`
5. Edit `.env` file in VS Code:
   - Change `DB_DATABASE=laravel` → `DB_DATABASE=my_database`
6. In Terminal, type:

```bash
php artisan migrate
```

7. Done! Your site can now save data.

---

## Recap (What You Did)
1. ✅ Installed Laragon
2. ✅ Created Laravel project
3. ✅ Opened it in browser
4. ✅ Changed the home page
5. ✅ (Optional) Added database

---

## If You Get Stuck
1. Make sure Laragon says **Start All** is green
2. Always type commands in Terminal (black window)
3. Files are in `C:\laragon\www\my-first-project\`
4. Your site is at `http://my-first-project.test`

---

## Next Steps You Can Try
- Add a "About Us" page
- Make a contact form
- Show data from database

That's it! You've built your first Laravel website. 🎉

---

## How EduVers' Homepage Was Built

EduVers's homepage (the one with "Senior High", "College", "Tracks & Strands") was built using the **exact same steps** above, just with more code.

### The Secret: It Shows Real Data

The three boxes are not hard‑coded text — they read from the database:

```php
// In app/Support/SchoolProfile.php
public static function tiles(): array
{
    return [
        'senior_high' => GradeLevel::where('level_type', LevelType::Shs)
                            ->withCount('students')->get(),
        'college'     => GradeLevel::where('level_type', LevelType::College)
                            ->withCount('students')->get(),
        'tracks'      => TrackStrand::withCount('students')->get(),
    ];
}
```

### How the Homepage Works
1. **Admin adds** Year Levels (Grade 11, Grade 12, 1st Year, etc.)
2. **Admin adds** Tracks & Strands (ICT, ABM, HRS, etc.)
3. **Admin adds** Sections (ICT 11‑A, HRS 1‑A, etc.)
4. **The homepage reads** those real lists and shows them
5. **If admin changes data tomorrow**, the homepage updates automatically

### Why This Is Better
- No hard‑coding — always shows what the school actually offers
- One place to edit (Admin → Year Levels / Tracks / Sections)
- Works for any school, not just Movers Institute

---

## Your Homework (For the Defense)
1. Show them Laragon starting
2. Show them your custom `welcome.blade.php`
3. Explain: *"EduVers works the same way, just with real data from the database"*
4. Open EduVers and show Admin → Year Levels → how changing there would update the homepage

**Boom — you've explained the entire system in 2 minutes.**

---

## How We Created the EduVers Login Page (SUPER SIMPLE)

**Follow these 3 steps exactly:**

### Step 1: Create ONE File
1. Open VS Code (Laragon → Right‑click your project → **Code**)
2. On the left, click `resources/views/`
3. Right‑click → **New File**
4. Name it: `login.blade.php`

### Step 2: Copy‑Paste This EXACT Code (Matches EduVers Design 100%)
Delete everything in the file, then paste **exactly**:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · EduVers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@500&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f4e8; color: #1a202c; }
        .login-grid { display: flex; min-height: 100vh; }
        /* LEFT PANEL (dark navy) */
        .left { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; flex: 1; padding: 60px 48px; position: relative; }
        .left::before { content: ''; position: absolute; top: -100px; left: -100px; width: 300px; height: 300px; background: radial-gradient(circle, #d4af3780 0%, transparent 70%); filter: blur(80px); }
        .logo { font-family: 'Playfair Display', serif; font-size: 28px; font-weight: 500; margin-bottom: 100px; color: #d4af37; }
        .tagline { font-size: 32px; line-height: 1.2; margin-bottom: 16px; color: #f8fafc; }
        .tagline span { color: #d4af37; }
        .motto { color: #94a3b8; font-style: italic; margin-bottom: 60px; }
        .subtext { color: #cbd5e1; font-size: 14px; margin-bottom: 40px; }
        /* Three boxes like EduVers */
        .info-row { display: flex; gap: 20px; margin-top: 40px; }
        .info-box { background: rgba(255,255,255,0.05); border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 12px; padding: 20px; flex: 1; }
        .info-box h4 { color: #d4af37; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .info-box p { color: #e2e8f0; font-size: 14px; }
        .info-box .plus { float: right; color: #94a3b8; cursor: pointer; }
        /* RIGHT PANEL (cream) */
        .right { flex: 1; padding: 80px 60px; display: flex; align-items: center; justify-content: center; }
        .form-card { max-width: 400px; width: 100%; }
        .eyebrow { color: #d4af37; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; }
        h1 { font-family: 'Playfair Display', serif; font-size: 32px; color: #0f172a; margin-bottom: 8px; }
        .form-sub { color: #64748b; margin-bottom: 32px; }
        /* Inputs */
        .input-group { margin-bottom: 24px; }
        label { display: block; color: #475569; font-size: 14px; font-weight: 500; margin-bottom: 6px; }
        input { width: 100%; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 15px; transition: border 0.2s; }
        input:focus { outline: none; border-color: #d4af37; box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1); }
        /* Password toggle */
        .password-wrap { position: relative; }
        .toggle-pw { position: absolute; right: 12px; top: 12px; background: none; border: none; color: #94a3b8; cursor: pointer; }
        /* Button */
        .btn { background: linear-gradient(135deg, #d4af37 0%, #b8941f 100%); color: #0f172a; border: none; border-radius: 8px; padding: 14px 24px; font-weight: 600; font-size: 15px; width: 100%; cursor: pointer; transition: opacity 0.2s; }
        .btn:hover { opacity: 0.9; }
        /* Footer */
        .footer { color: #94a3b8; font-size: 12px; text-align: center; margin-top: 40px; }
    </style>
</head>
<body>
    <div class="login-grid">
        {{-- LEFT PANEL (dark) --}}
        <div class="left">
            <div class="logo">MOVERS INSTITUTE OF TECHNOLOGY AND EDUCATION</div>
            <h2 class="tagline">Where excellence is <span>taught, earned,</span> and measured.</h2>
            <p class="motto">— Transform, Learn, Lead</p>
            <p class="subtext">Lessons, quizzes and progress for Senior High School and College, all in one place.</p>
            
            <div class="info-row">
                <div class="info-box">
                    <h4>SENIOR HIGH<span class="plus" onclick="alert('Grade 11\nGrade 12')">+</span></h4>
                    <p>Grade 11 & Grade 12</p>
                </div>
                <div class="info-box">
                    <h4>COLLEGE<span class="plus" onclick="alert('BSIT\nHRS')">+</span></h4>
                    <p>BSIT & HRS</p>
                </div>
                <div class="info-box">
                    <h4>TRACKS<span class="plus" onclick="alert('Academic\nCollege\nTVL\nTechPro')">+</span></h4>
                    <p>Academic, College, TVL & TechPro</p>
                </div>
            </div>
        </div>

        {{-- RIGHT PANEL (cream) --}}
        <div class="right">
            <div class="form-card">
                <p class="eyebrow">WELCOME BACK</p>
                <h1>Sign in to EduVers</h1>
                <p class="form-sub">Use the account provided by your institution.</p>
                
                <form>
                    <div class="input-group">
                        <label>Email address</label>
                        <input type="email" placeholder="you@movers.edu.ph" value="admin@eduvers.test">
                    </div>
                    
                    <div class="input-group">
                        <label>Password</label>
                        <div class="password-wrap">
                            <input type="password" placeholder="••••••••" value="Admin123" id="password">
                            <button type="button" class="toggle-pw" onclick="
                                let pw = document.getElementById('password');
                                pw.type = pw.type === 'password' ? 'text' : 'password';
                            ">👁</button>
                        </div>
                    </div>
                    
                    <button type="button" class="btn" onclick="alert('Signed in as admin@eduvers.test')">Sign in</button>
                </form>
                
                <p class="footer">© 2026 Movers Institute · EduVers Learning Management System</p>
            </div>
        </div>
    </div>
</body>
</html>
```

### Step 3: Add a Route (This Makes It Visible)
1. Open `routes/web.php`
2. Add this line **anywhere inside the file**:

```php
Route::get('/login', function () {
    return view('login');
});
```

Your `routes/web.php` will now look like:
```php
<?php

use Illuminate\Support\Facades\Route;

Route::get('/login', function () {
    return view('login');
});

// Other routes...
```

### Step 4: Open It in Browser
1. In Laragon, find your project in the list
2. Right‑click → **Quick add → www**
3. Click **Start All**
4. Go to: `http://YOUR-PROJECT.test/login`

You'll see your own login page!

---

## The Secret Behind EduVers' Real Data
**In EduVers, we replaced the hard‑coded text with database calls:**

Instead of:
```html
<p>Grade 11 & Grade 12</p>
```

We use:
```php
@foreach($seniorHighLevels as $level)
    <p>{{ $level->name }}</p>
@endforeach
```

Where `$seniorHighLevels` comes from:
```php
GradeLevel::where('level_type', 'shs')->get();
```

### How to Explain in the Defense
1. **Show your simple login page** (`http://YOUR-PROJECT.test/login`)
2. **Say:** "This is static HTML — everything is written in the file"
3. **Open EduVers login page** (`http://eduvers.test`)
4. **Say:** "This is dynamic — it reads from the database"
5. **Show the database:** Laragon → MySQL → `grade_levels` table
6. **Conclusion:** "Same design, but EduVers uses real school data!"

---

## What Your Students Can Show in the Defense
1. **Open VS Code** → Show `resources/views/auth/login.blade.php`
2. **Point at the two `<div>`s** → "This makes left/right panels"
3. **Point at the `@foreach` loop** → "This reads real Grade Levels from database"
4. **Show the database** → Laragon → MySQL → `grade_levels` table
5. **Demonstrate**: Change a grade level name in the database, refresh the login page → it updates!

**That's the whole magic!** The page looks fancy but it's just:
- HTML/CSS layout
- PHP reading from database
- JavaScript for the "+" buttons

They can now explain exactly how the login page works to the panel.

