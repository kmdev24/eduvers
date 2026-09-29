<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AcademicTermController extends Controller
{
    private const ORDINALS = [1 => '1st Term', 2 => '2nd Term', 3 => '3rd Term', 4 => '4th Term'];

    public function index(): View
    {
        $terms = AcademicTerm::withCount(['subjects', 'lessons', 'quizzes'])
            ->orderByDesc('academic_year')
            ->orderBy('term_number')
            ->get()
            ->groupBy(fn (AcademicTerm $t) => $t->academic_year ?? 'No academic year');

        return view('developer.terms.index', [
            'termsByYear' => $terms,
            'currentTerm' => AcademicTerm::getCurrent(),
            'nextYear'    => $this->suggestNextYear(),
        ]);
    }

    /** Create one term. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('term', $this->rules($request));
        $term = AcademicTerm::create($data);

        if (! AcademicTerm::current()->exists()) {
            $term->makeCurrent();
        }

        return back()->with('status', "{$term->name} ({$term->academic_year}) was created.");
    }

    /** Create all terms (1–3 by default) for an academic year in one go. */
    public function storeYear(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('year', [
            'academic_year' => ['required', 'string', $this->yearRule()],
            'terms'         => ['required', 'integer', 'min:1', 'max:4'],
        ]);

        $created = DB::transaction(function () use ($data) {
            $count = 0;
            foreach (range(1, (int) $data['terms']) as $n) {
                $term = AcademicTerm::firstOrCreate(
                    ['academic_year' => $data['academic_year'], 'term_number' => $n],
                    ['name' => self::ORDINALS[$n], 'is_current' => false],
                );
                $count += (int) $term->wasRecentlyCreated;
            }

            return $count;
        });

        if (! AcademicTerm::current()->exists()) {
            AcademicTerm::where('academic_year', $data['academic_year'])->orderBy('term_number')->first()?->makeCurrent();
        }

        return back()->with('status', $created > 0
            ? "Created {$created} term(s) for {$data['academic_year']}."
            : "All terms for {$data['academic_year']} already exist.");
    }

    public function edit(AcademicTerm $term): View
    {
        return view('developer.terms.edit', ['term' => $term]);
    }

    public function update(Request $request, AcademicTerm $term): RedirectResponse
    {
        $term->update($request->validate($this->rules($request, $term)));

        return redirect()->route('developer.terms.index')->with('status', "{$term->name} was updated.");
    }

    public function makeCurrent(AcademicTerm $term): RedirectResponse
    {
        $term->makeCurrent();

        return back()->with('status', "{$term->name} ({$term->academic_year}) is now the current term.");
    }

    public function destroy(AcademicTerm $term): RedirectResponse
    {
        if ($term->is_current) {
            return back()->with('error', 'You cannot delete the current term. Set another term as current first.');
        }

        if ($term->subjects()->exists() || $term->lessons()->exists() || $term->quizzes()->exists()) {
            return back()->with('error', "{$term->name} still has subjects, lessons or quizzes and can't be deleted.");
        }

        $name = $term->name;
        $term->delete();

        return back()->with('status', "{$name} was deleted.");
    }

    /* ------------------------------------------------------------------ */

    private function rules(Request $request, ?AcademicTerm $term = null): array
    {
        return [
            'name'          => ['required', 'string', 'max:50'],
            'academic_year' => ['required', 'string', $this->yearRule()],
            'term_number'   => [
                'required', 'integer', 'min:1', 'max:4',
                Rule::unique('academic_terms', 'term_number')
                    ->where('academic_year', $request->input('academic_year'))
                    ->ignore($term),
            ],
        ];
    }

    /** "2026-2027": four digits, a dash, and the following year. */
    private function yearRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! preg_match('/^(\d{4})-(\d{4})$/', (string) $value, $m) || (int) $m[2] !== (int) $m[1] + 1) {
                $fail('Use the format 2026-2027 (the second year must follow the first).');
            }
        };
    }

    private function suggestNextYear(): string
    {
        $latest = AcademicTerm::whereNotNull('academic_year')->max('academic_year');
        $start  = $latest ? (int) substr($latest, 0, 4) + 1 : (int) now()->format('Y');

        return $start.'-'.($start + 1);
    }
}
