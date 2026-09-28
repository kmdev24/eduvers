@use('App\Enums\TeacherType')
@use('App\Enums\UserRole')

<x-layouts.app title="Developer Dashboard">

    {{-- Institutional banner --}}
    <section class="relative mb-8 overflow-hidden rounded-2xl bg-ink-gradient p-6 text-ivory shadow-elevated sm:p-8">
        <div class="pointer-events-none absolute -top-24 -right-16 size-72 rounded-full bg-gold-500/20 blur-3xl"></div>
        <div class="pointer-events-none absolute inset-0 opacity-[0.035]"
             style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 40px 40px;"></div>
        <div class="relative flex flex-col gap-6 md:flex-row md:items-center">
            <x-logo class="size-20 ring-4" />
            <div class="flex-1">
                <p class="text-xs font-semibold uppercase tracking-luxe text-gold-400">Developer portal</p>
                <h2 class="mt-1 font-serif text-2xl font-semibold sm:text-3xl">Movers Institute of Technology and Education</h2>
                <p class="mt-2 font-serif text-lg text-gold-300 italic">Transform, Learn, Lead</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('announcements.index') }}" class="btn-gold"><x-icon name="megaphone" class="size-4" /> Post announcement</a>
                <a href="{{ route('developer.users.create') }}" class="inline-flex items-center gap-2 rounded-xl border border-white/15 px-4 py-2.5 text-sm font-semibold text-ivory transition hover:border-gold-400 hover:text-gold-300"><x-icon name="user-plus" class="size-4" /> Add user</a>
            </div>
        </div>
    </section>

    {{-- Heading --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">System overview</p>
            <h1 class="heading-serif mt-2 text-3xl sm:text-4xl">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ strtok(auth()->user()->name, ' ') }}.</h1>
            <p class="mt-2 text-slate-500">Here's how EduVers looks across the institute today.</p>
        </div>
    </div>

    {{-- Stats --}}
    <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Students" :value="number_format($stats['students'])" icon="academic-cap" hint="Enrolled student accounts" />
        <x-stat-card label="Teachers" :value="number_format($stats['teachers'])" icon="users" hint="Active faculty accounts" />
        <x-stat-card label="Sections" :value="number_format($stats['sections'])" icon="building" hint="Across SHS and College" />
        <x-stat-card label="Subjects" :value="number_format($stats['subjects'])" icon="stack" hint="Offered this academic year" />
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-3">

        {{-- Sections & capacity --}}
        <div class="card xl:col-span-2">
            <div class="flex items-center justify-between border-b border-ivory-300 px-6 py-5">
                <div>
                    <h2 class="font-serif text-lg font-semibold">Sections &amp; capacity</h2>
                    <p class="text-sm text-slate-500">Enrolment against each section's limit</p>
                </div>
                <a href="{{ route('developer.sections.index') }}" class="text-sm font-medium text-gold-700 hover:text-gold-900">Manage sections →</a>
            </div>

            @if ($sections->isEmpty())
                <x-empty-state icon="building" title="No sections yet" message="Create sections to start enrolling students." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wider text-slate-400">
                                <th class="px-6 py-3 font-semibold">Section</th>
                                <th class="px-6 py-3 font-semibold">Level &amp; strand</th>
                                <th class="px-6 py-3 font-semibold">Enrolled</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ivory-200">
                            @foreach ($sections as $section)
                                @php $fill = $section->capacity > 0 ? min(100, round($section->students_count / $section->capacity * 100)) : 0; @endphp
                                <tr class="transition hover:bg-ivory-50">
                                    <td class="px-6 py-4 font-medium text-ink-900">{{ $section->name }}</td>
                                    <td class="px-6 py-4 text-slate-500">
                                        {{ $section->gradeLevel->name }}
                                        <span class="badge-slate ml-1">{{ $section->trackStrand->name }}</span>
                                    </td>
                                    <td class="w-64 px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-ivory-200">
                                                <div class="h-full rounded-full {{ $fill >= 100 ? 'bg-red-400' : 'bg-gold-gradient' }}" style="width: {{ $fill }}%"></div>
                                            </div>
                                            <span class="w-16 text-right text-xs tabular-nums text-slate-500">{{ $section->students_count }} / {{ $section->capacity }}</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            {{-- Current term --}}
            <div class="relative overflow-hidden rounded-2xl bg-ink-gradient p-6 text-ivory shadow-elevated">
                <div class="pointer-events-none absolute -top-16 -right-16 size-48 rounded-full bg-gold-500/20 blur-2xl"></div>
                <p class="relative text-xs font-semibold uppercase tracking-luxe text-gold-400">Current term</p>
                @if ($currentTerm)
                    <p class="relative mt-3 font-serif text-3xl font-semibold">{{ $currentTerm->name }}</p>
                    <p class="relative mt-1 text-sm text-slate-300">Academic Year {{ $currentTerm->academic_year ?? '—' }}</p>
                    <div class="relative mt-5 flex gap-1.5">
                        @foreach ([1, 2, 3] as $n)
                            <span class="h-1.5 flex-1 rounded-full {{ $n <= $currentTerm->term_number ? 'bg-gold-gradient' : 'bg-white/10' }}"></span>
                        @endforeach
                    </div>
                @else
                    <p class="relative mt-3 text-sm text-slate-300">No term is marked as current yet.</p>
                @endif
            </div>

            {{-- Faculty composition --}}
            <div class="card p-6">
                <h2 class="font-serif text-lg font-semibold">Faculty composition</h2>
                <p class="text-sm text-slate-500">Teachers by employment type</p>
                @php $facultyTotal = max(1, $stats['teachers']); @endphp
                <ul class="mt-5 space-y-4">
                    @foreach (TeacherType::cases() as $type)
                        @php $count = (int) ($teacherTypes[$type->value] ?? 0); @endphp
                        <li>
                            <div class="flex justify-between text-sm">
                                <span class="text-ink-800">{{ $type->label() }}</span>
                                <span class="tabular-nums text-slate-500">{{ $count }}</span>
                            </div>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-ivory-200">
                                <div class="h-full rounded-full bg-gold-gradient" style="width: {{ round($count / $facultyTotal * 100) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                    @php $unassigned = (int) ($teacherTypes[''] ?? 0); @endphp
                    @if ($unassigned > 0)
                        <li class="text-xs text-slate-400">{{ $unassigned }} teacher(s) without a type set</li>
                    @endif
                </ul>
            </div>
        </div>
    </div>

    {{-- Recent accounts --}}
    <div class="card mt-6">
        <div class="flex items-center justify-between border-b border-ivory-300 px-6 py-5">
            <div>
                <h2 class="font-serif text-lg font-semibold">Recently added accounts</h2>
                <p class="text-sm text-slate-500">The newest users in the system</p>
            </div>
            <a href="{{ route('developer.users.index') }}" class="text-sm font-medium text-gold-700 hover:text-gold-900">All users →</a>
        </div>
        <ul class="divide-y divide-ivory-200">
            @forelse ($recentUsers as $account)
                <li class="flex items-center gap-4 px-6 py-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-ivory-200 text-sm font-semibold text-ink-800 ring-1 ring-ivory-300">{{ $account->initials() }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-ink-900">{{ $account->name }}</p>
                        <p class="truncate text-sm text-slate-500">{{ $account->email }}</p>
                    </div>
                    <span class="{{ $account->role === UserRole::Developer ? 'badge-gold' : 'badge-slate' }}">
                        {{ $account->role->label() }}@if ($account->teacher_type) · {{ $account->teacher_type->label() }}@endif
                    </span>
                    <span class="hidden w-28 text-right text-xs text-slate-400 sm:block">{{ $account->created_at?->diffForHumans() }}</span>
                </li>
            @empty
                <li><x-empty-state icon="users" title="No accounts yet" /></li>
            @endforelse
        </ul>
    </div>
</x-layouts.app>
