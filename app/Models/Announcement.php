<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'body',
        'audience',
        'section_id',
        'author_id',
        'is_pinned',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'audience'   => AnnouncementAudience::class,
            'is_pinned'  => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo  { return $this->belongsTo(User::class, 'author_id'); }
    public function section(): BelongsTo { return $this->belongsTo(Section::class); }

    /* Scopes ----------------------------------------------------------- */

    /** Not yet expired. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** Pinned first, then newest. */
    public function scopeFeed(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')->latest();
    }

    /** Announcements a given user is allowed to see. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isDeveloper()) {
            return $query;
        }

        if ($user->isTeacher()) {
            $sectionIds = SubjectTeacher::where('teacher_id', $user->id)->select('section_id');

            return $query->where(fn ($q) => $q
                ->whereIn('audience', [AnnouncementAudience::Everyone->value, AnnouncementAudience::Teachers->value])
                ->orWhere('author_id', $user->id)
                ->orWhere(fn ($q) => $q
                    ->where('audience', AnnouncementAudience::Section->value)
                    ->whereIn('section_id', $sectionIds)));
        }

        return $query->where(fn ($q) => $q
            ->whereIn('audience', [AnnouncementAudience::Everyone->value, AnnouncementAudience::Students->value])
            ->orWhere(fn ($q) => $q
                ->where('audience', AnnouncementAudience::Section->value)
                ->where('section_id', $user->section_id ?? 0)));
    }

    /* Helpers ---------------------------------------------------------- */

    public function audienceLabel(): string
    {
        return $this->audience === AnnouncementAudience::Section && $this->section
            ? $this->section->name
            : $this->audience->label();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
