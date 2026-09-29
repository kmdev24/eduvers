<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** A single announcement — the target of announcement notifications. */
class AnnouncementController extends Controller
{
    public function show(Request $request, Announcement $announcement): View
    {
        abort_unless(
            Announcement::query()->visibleTo($request->user())->whereKey($announcement->id)->exists(),
            404
        );

        return view('student.announcements.show', [
            'announcement' => $announcement->load(['author', 'section']),
        ]);
    }
}
