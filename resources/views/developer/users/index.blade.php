@use('App\Enums\UserRole')
@use('App\Enums\TeacherType')

<x-layouts.app title="User Management">
    <x-page-header eyebrow="Administration" title="Users"
                   description="Create and manage developer, teacher and student accounts.">
        <x-slot:actions>
            <a href="{{ route('developer.users.create') }}" class="btn-gold">
                <x-icon name="user-plus" class="size-4" /> Add user
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Role tabs --}}
    @php
        $total = $roleCounts->sum();
        $tabs  = collect([['value' => null, 'label' => 'All users', 'count' => $total]])
            ->concat(collect(UserRole::cases())->map(fn ($r) => [
                'value' => $r->value, 'label' => \Illuminate\Support\Str::plural($r === UserRole::Developer ? 'Developer' : $r->label()), 'count' => (int) ($roleCounts[$r->value] ?? 0),
            ]));
    @endphp
    <div class="mt-8 flex flex-wrap gap-2">
        @foreach ($tabs as $tab)
            @php $isActive = $filters['role'] === $tab['value']; @endphp
            <a href="{{ route('developer.users.index', array_filter(['role' => $tab['value'], 'q' => $filters['q']])) }}"
               @class([
                   'inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition',
                   'bg-ink-900 text-ivory shadow-soft' => $isActive,
                   'bg-white text-slate-600 ring-1 ring-ivory-300 hover:text-ink-900 hover:ring-gold-300' => ! $isActive,
               ])>
                {{ $tab['label'] }}
                <span @class(['rounded-full px-2 py-0.5 text-xs tabular-nums', 'bg-gold-500 text-ink-900' => $isActive, 'bg-ivory-200 text-slate-500' => ! $isActive])>{{ $tab['count'] }}</span>
            </a>
        @endforeach
    </div>

    <div class="card mt-6">
        {{-- Filters --}}
        <form method="GET" action="{{ route('developer.users.index') }}" class="flex flex-col gap-3 border-b border-ivory-300 p-5 md:flex-row md:items-center">
            @if ($filters['role'])
                <input type="hidden" name="role" value="{{ $filters['role'] }}">
            @endif
            <div class="relative flex-1">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search by name or email" class="input pl-10">
            </div>
            <select name="teacher_type" class="input md:w-56" aria-label="Teacher type">
                <option value="">All teacher types</option>
                @foreach (TeacherType::cases() as $type)
                    <option value="{{ $type->value }}" @selected($filters['teacher_type'] === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="btn-dark">Filter</button>
                @if ($filters['q'] || $filters['teacher_type'] || $filters['role'])
                    <a href="{{ route('developer.users.index') }}" class="btn-ghost">Reset</a>
                @endif
            </div>
        </form>

        {{-- Table --}}
        @if ($users->isEmpty())
            <x-empty-state icon="users" title="No users found" message="Try a different filter, or add a new user." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="table-head">
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Role</th>
                            <th class="px-6 py-3">Details</th>
                            <th class="px-6 py-3">Added</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ivory-200">
                        @foreach ($users as $account)
                            <tr class="transition hover:bg-ivory-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-ivory-200 text-sm font-semibold text-ink-800 ring-1 ring-ivory-300">{{ $account->initials() }}</span>
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-ink-900">
                                                {{ $account->name }}
                                                @if ($account->is(auth()->user()))
                                                    <span class="ml-1 text-xs font-normal text-champagne-dark">(you)</span>
                                                @endif
                                            </p>
                                            <p class="truncate text-slate-500">{{ $account->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="{{ $account->isDeveloper() ? 'badge-gold' : 'badge-slate' }}">{{ $account->role->label() }}</span>
                                </td>
                                <td class="px-6 py-4 text-slate-600">
                                    @if ($account->isTeacher())
                                        {{ $account->teacher_type?->label() ?? 'Type not set' }}
                                    @elseif ($account->isStudent())
                                        @if ($account->section)
                                            {{ $account->section->name }} <span class="text-slate-400">· {{ $account->section->gradeLevel->name }}</span>
                                        @else
                                            <span class="text-amber-700">No section</span>
                                        @endif
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-slate-500">{{ $account->created_at?->format('M j, Y') }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('developer.users.edit', $account) }}" class="icon-btn" title="Edit" aria-label="Edit {{ $account->name }}">
                                            <x-icon name="pencil" class="size-5" />
                                        </a>
                                        <form method="POST" action="{{ route('developer.users.destroy', $account) }}"
                                              data-confirm="Delete {{ $account->name }}? This can't be undone.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="icon-btn-danger" title="Delete" aria-label="Delete {{ $account->name }}"
                                                    @disabled($account->is(auth()->user()))>
                                                <x-icon name="trash" class="size-5" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-ivory-300 px-6 py-4">
                {{ $users->links('partials.pagination') }}
            </div>
        @endif
    </div>
</x-layouts.app>
