<?php

namespace App\Http\Controllers\Developer;

use App\Enums\TrackCategory;
use App\Http\Controllers\Controller;
use App\Models\TrackStrand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tracks / strands / programs (e.g. ICT, ABM, TVL-HE, HRS) grouped by category.
 */
class TrackStrandController extends Controller
{
    public function index(): View
    {
        $strands = TrackStrand::withCount(['sections', 'subjects'])->orderBy('name')->get();

        return view('developer.tracks.index', [
            'categories' => collect(TrackCategory::cases())->map(fn (TrackCategory $c) => [
                'category' => $c,
                'strands'  => $strands->filter(fn (TrackStrand $s) => $s->category === $c)->values(),
            ]),
            'total' => $strands->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('strand', [
            'name'     => ['required', 'string', 'max:50', Rule::unique('track_strands', 'name')],
            'category' => ['required', Rule::enum(TrackCategory::class)],
        ]);

        $strand = TrackStrand::create($data);

        return back()->with('status', "{$strand->name} was added to {$strand->category->label()}.");
    }

    public function edit(TrackStrand $strand): View
    {
        return view('developer.tracks.edit', [
            'strand' => $strand->loadCount(['sections', 'subjects']),
        ]);
    }

    public function update(Request $request, TrackStrand $strand): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:50', Rule::unique('track_strands', 'name')->ignore($strand)],
            'category' => ['required', Rule::enum(TrackCategory::class)],
        ]);

        // Moving between SHS and College would break existing sections
        $newCategory = TrackCategory::from($data['category']);
        if ($newCategory->isCollege() !== $strand->category->isCollege() && $strand->sections()->exists()) {
            return back()->withInput()->withErrors([
                'category' => "{$strand->name} is used by sections, so it can't move between SHS and College.",
            ]);
        }

        $strand->update($data);

        return redirect()->route('developer.tracks.index')->with('status', "{$strand->name} was updated.");
    }

    public function destroy(TrackStrand $strand): RedirectResponse
    {
        if ($strand->sections()->exists()) {
            return back()->with('error', "{$strand->name} is still used by sections and can't be deleted.");
        }

        $name = $strand->name;
        $strand->delete(); // subjects under it become core subjects

        return back()->with('status', "{$name} was deleted.");
    }
}
