<?php

namespace App\Support;

use App\Enums\LevelType;
use App\Enums\TrackCategory;
use App\Models\GradeLevel;
use App\Models\TrackStrand;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * What the school offers, for the three panels on the sign-in page.
 *
 * Everything is read from the year levels, strands and sections an admin maintains, so
 * the sign-in page can never advertise a course the school no longer runs. To change what
 * a visitor sees, change the data: Admin → Year Levels, Tracks & Strands, Sections.
 */
class SchoolProfile
{
    /**
     * @return array<int, array{key: string, label: string, value: string, title: string,
     *                          note: string, rows: array<int, array{name: string, detail: string}>}>
     */
    public static function tiles(): array
    {
        $levels  = GradeLevel::with('sections.trackStrand')->orderBy('id')->get();
        $strands = TrackStrand::withCount('sections')->orderBy('name')->get();

        return [
            self::levels('senior-high', 'Senior High', LevelType::Shs, $levels),
            self::levels('college', 'College', LevelType::College, $levels),
            self::tracks($strands),
        ];
    }

    /** One tile per programme: its year levels, and the strands actually running in each. */
    private static function levels(string $key, string $label, LevelType $type, Collection $all): array
    {
        $levels = $all->where('level_type', $type)->values();

        $rows = $levels->map(fn (GradeLevel $level) => [
            'name'   => $level->name,
            'detail' => self::list($level->sections->pluck('trackStrand.name')) ?: 'No sections yet',
        ])->all();

        // College is known by its courses, Senior High by its grade levels
        $value = $type === LevelType::College
            ? self::list($levels->flatMap(fn (GradeLevel $l) => $l->sections->pluck('trackStrand.name')))
            : self::list($levels->pluck('name'));

        return [
            'key'   => $key,
            'label' => $label,
            'value' => $value ?: 'Coming soon',
            'title' => $type->label(),
            'note'  => $type === LevelType::College
                ? 'Courses running in each year level.'
                : 'Strands running in each grade level.',
            'rows'  => $rows,
        ];
    }

    /** The strands and courses themselves, grouped the way the school groups them. */
    private static function tracks(Collection $strands): array
    {
        $rows = collect(TrackCategory::cases())
            ->map(fn (TrackCategory $category) => [
                'name'   => $category->label(),
                'detail' => self::list($strands->where('category', $category)->pluck('name')),
            ])
            ->filter(fn (array $row) => $row['detail'] !== '')
            ->values()
            ->all();

        return [
            'key'   => 'tracks',
            'label' => 'Tracks',
            'value' => self::list(collect($rows)->pluck('name')) ?: 'Coming soon',
            'title' => 'Tracks, strands & courses',
            'note'  => 'Every strand and course the school offers.',
            'rows'  => $rows,
        ];
    }

    /** "ICT", "ICT & ABM", "ICT, ABM & STEM" — unique, in order, nulls dropped. */
    private static function list(Collection $names): string
    {
        return Arr::join($names->filter()->unique()->sort()->values()->all(), ', ', ' & ');
    }
}
