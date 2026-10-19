@use('App\Enums\EnrollmentStatus')

<x-layouts.app title="Enrollment requests">
    <x-page-header eyebrow="Administration" title="Enrollment requests"
                   description="Teachers and students ask to add a student to a class outside the student's section. Nothing changes until you approve." />

    <div class="mt-8 flex flex-wrap gap-2">
        @foreach (EnrollmentStatus::cases() as $tab)
            @php $isActive = $status === $tab; @endphp
            <a href="{{ route('admin.enrollments.index', ['status' => $tab->value]) }}"
               @class([
                   'inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition',
                   'bg-ink-900 text-ivory shadow-soft' => $isActive,
                   'bg-white text-slate-600 ring-1 ring-ivory-300 hover:text-ink-900 hover:ring-gold-300' => ! $isActive,
               ])>
                {{ $tab === EnrollmentStatus::Pending ? 'Waiting' : $tab->label() }}
                <span @class(['rounded-full px-2 py-0.5 text-xs tabular-nums', 'bg-gold-500 text-ink-900' => $isActive, 'bg-ivory-200 text-slate-500' => ! $isActive])>{{ (int) ($counts[$tab->value] ?? 0) }}</span>
            </a>
        @endforeach
    </div>

    <div class="card mt-6">
        @if ($enrollments->isEmpty())
            <x-empty-state icon="user-plus" title="Nothing here" :message="$status === EnrollmentStatus::Pending ? 'No requests are waiting for approval.' : 'No requests with this status.'" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="table-head">
                            <th class="px-6 py-3">Student</th>
                            <th class="px-6 py-3">Class to join</th>
                            <th class="px-6 py-3">Asked by</th>
                            <th class="px-6 py-3 text-right">Decision</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ivory-200">
                        @foreach ($enrollments as $enrollment)
                            <tr class="transition hover:bg-ivory-50">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-ink-900">{{ $enrollment->student->name }}</p>
                                    <p class="text-xs break-all text-slate-500">{{ $enrollment->student->email }} · {{ $enrollment->student->section?->name ?? 'No section' }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-medium text-ink-900">{{ $enrollment->offering->subject->code }} · {{ $enrollment->offering->subject->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $enrollment->offering->section->name }} · {{ $enrollment->offering->teacher?->name ?? 'No teacher yet' }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-slate-700">{{ $enrollment->requester?->name ?? 'Deleted user' }}</p>
                                    <p class="text-xs text-slate-500">{{ $enrollment->requester?->role->label() }} · {{ $enrollment->created_at->diffForHumans() }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        @if ($enrollment->status !== EnrollmentStatus::Pending)
                                            <span class="text-xs text-slate-500">{{ $enrollment->decider?->name }} · {{ $enrollment->decided_at?->diffForHumans() }}</span>
                                        @endif
                                        @if ($enrollment->status !== EnrollmentStatus::Approved)
                                            <form method="POST" action="{{ route('admin.enrollments.update', $enrollment) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="approved">
                                                <button type="submit" class="btn-gold px-4 py-2"><x-icon name="check" class="size-4" /> Approve</button>
                                            </form>
                                        @endif
                                        @if ($enrollment->status !== EnrollmentStatus::Rejected)
                                            <form method="POST" action="{{ route('admin.enrollments.update', $enrollment) }}"
                                                  @if ($enrollment->status === EnrollmentStatus::Approved) data-confirm="Remove {{ $enrollment->student->name }} from this class?" @endif>
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="rejected">
                                                <button type="submit" class="btn-ghost px-4 py-2">{{ $enrollment->status === EnrollmentStatus::Approved ? 'Remove' : 'Reject' }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-ivory-300 px-6 py-4">
                {{ $enrollments->links('partials.pagination') }}
            </div>
        @endif
    </div>
</x-layouts.app>
