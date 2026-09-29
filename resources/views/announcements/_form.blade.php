{{-- Announcement fields. Expects $announcement, $audiences, $sections. --}}
@use('App\Enums\AnnouncementAudience')
@php
    $currentAudience = old('audience', $announcement->audience?->value ?? $audiences[0]->value);
    $onlySection     = count($audiences) === 1;
@endphp

<x-field label="Title" name="title">
    <input id="title" name="title" type="text" maxlength="150" value="{{ old('title', $announcement->title) }}" required class="input" placeholder="Classes suspended on Friday">
</x-field>

<x-field label="Message" name="body">
    <textarea id="body" name="body" rows="6" maxlength="5000" required class="input" placeholder="Write the announcement…">{{ old('body', $announcement->body) }}</textarea>
</x-field>

@if ($onlySection)
    <input type="hidden" name="audience" value="{{ AnnouncementAudience::Section->value }}">
@else
    <x-field label="Who should see this?" name="audience">
        <select id="audience" name="audience" class="input" data-audience-select>
            @foreach ($audiences as $audience)
                <option value="{{ $audience->value }}" @selected($currentAudience === $audience->value)>{{ $audience->label() }}</option>
            @endforeach
        </select>
    </x-field>
@endif

<div data-audience-section @class(['hidden' => ! $onlySection && $currentAudience !== AnnouncementAudience::Section->value])>
    <x-field label="Section" name="section_id" :hint="$onlySection ? 'Only students in this section will see it.' : null">
        <select id="section_id" name="section_id" class="input">
            <option value="">Choose a section…</option>
            @foreach ($sections->groupBy(fn ($s) => $s->gradeLevel->name) as $level => $group)
                <optgroup label="{{ $level }}">
                    @foreach ($group as $section)
                        <option value="{{ $section->id }}" @selected((string) old('section_id', $announcement->section_id) === (string) $section->id)>{{ $section->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </x-field>
    @if ($sections->isEmpty())
        <p class="mt-2 text-sm text-amber-700">You aren't assigned to any section yet.</p>
    @endif
</div>

<div class="grid gap-5 sm:grid-cols-2">
    <x-field label="Expires on (optional)" name="expires_at" hint="Hidden from dashboards after this date.">
        <input id="expires_at" name="expires_at" type="datetime-local" class="input"
               value="{{ old('expires_at', $announcement->expires_at?->format('Y-m-d\TH:i')) }}">
    </x-field>
    <label class="flex items-center gap-3 self-end rounded-xl border border-ivory-300 px-4 py-3 text-sm text-slate-700 sm:mb-6">
        <input type="hidden" name="is_pinned" value="0">
        <input type="checkbox" name="is_pinned" value="1" class="rounded border-ivory-300 text-gold-600 focus:ring-gold-500/40" @checked(old('is_pinned', $announcement->is_pinned))>
        <x-icon name="star" class="size-4 text-gold-600" /> Pin to the top
    </label>
</div>
