<x-layouts.app title="System check">
    <x-page-header eyebrow="Developer tools" title="System check"
                   description="Is the notification and email pipeline healthy? Send a test to any student to see exactly what happens." />

    <div class="mt-8 grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">

            {{-- Test --}}
            <section class="card p-6">
                <h2 class="font-serif text-lg font-semibold">Send a test notification</h2>
                <p class="mt-1 text-sm text-slate-500">Runs immediately (no queue), so any error is shown here instead of only in the server logs.</p>

                <form method="POST" action="{{ route('developer.system.test') }}" class="mt-5 grid gap-4 sm:grid-cols-[1fr_auto_auto] sm:items-end">
                    @csrf
                    <x-field label="Student" name="student_id">
                        <select id="student_id" name="student_id" class="input" required>
                            <option value="">Choose a student…</option>
                            @foreach ($students as $student)
                                <option value="{{ $student->id }}" @selected(old('student_id') == $student->id)>
                                    {{ $student->name }} — {{ $student->section?->name ?? 'no section' }} ({{ $student->email }})
                                </option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field label="Send" name="channels">
                        <select id="channels" name="channels" class="input">
                            <option value="database">In-app only</option>
                            <option value="both">In-app + email</option>
                        </select>
                    </x-field>
                    <button type="submit" class="btn-gold">Send test</button>
                </form>
            </section>

            {{-- Recent notifications --}}
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-ivory-300 px-6 py-4">
                    <h2 class="font-serif text-lg font-semibold">Recent notifications</h2>
                    <span class="badge-slate">{{ $hasNotifications ? $notificationCount.' total' : 'table missing' }}</span>
                </div>
                @if (! $hasNotifications)
                    <p class="px-6 py-6 text-sm text-red-700">The <code>notifications</code> table doesn't exist. Run <code>php artisan migrate</code> (Railway does this on deploy when RUN_MIGRATIONS=true).</p>
                @elseif ($recent->isEmpty())
                    <p class="px-6 py-6 text-sm text-slate-500">No notifications have been created yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="table-head">
                                <tr><th class="px-6 py-3 text-left">When</th><th class="px-6 py-3 text-left">Student</th><th class="px-6 py-3 text-left">What</th><th class="px-6 py-3 text-left">Read</th></tr>
                            </thead>
                            <tbody class="divide-y divide-ivory-200">
                                @foreach ($recent as $n)
                                    <tr>
                                        <td class="px-6 py-3 whitespace-nowrap text-slate-500">{{ $n->created_at?->diffForHumans() }}</td>
                                        <td class="px-6 py-3">{{ $n->notifiable?->name ?? '—' }}</td>
                                        <td class="px-6 py-3"><span class="text-xs text-champagne-dark">{{ $n->data['headline'] ?? '' }}</span><br>{{ $n->data['title'] ?? '' }}</td>
                                        <td class="px-6 py-3">{{ $n->read_at ? 'Yes' : 'No' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Failed jobs --}}
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-ivory-300 px-6 py-4">
                    <h2 class="font-serif text-lg font-semibold">Failed emails (failed_jobs)</h2>
                    <span class="{{ $failedCount ? 'badge-gold' : 'badge-slate' }}">{{ $failedCount ?? '—' }}</span>
                </div>
                @forelse ($failedJobs as $job)
                    <div class="border-b border-ivory-200 px-6 py-3 text-sm last:border-0">
                        <p class="text-xs text-slate-400">{{ $job['at'] }}</p>
                        <p class="mt-1 font-mono text-xs break-words text-red-700">{{ $job['error'] }}</p>
                    </div>
                @empty
                    <p class="px-6 py-6 text-sm text-slate-500">No failed jobs.</p>
                @endforelse
            </section>
        </div>

        <div class="space-y-6">
            {{-- Settings --}}
            <section class="card p-6">
                <h2 class="font-serif text-lg font-semibold">Settings in use</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    @foreach ($settings as $label => $value)
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="text-right font-medium break-all text-ink-900">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            {{-- Queue --}}
            <section class="card p-6">
                <h2 class="font-serif text-lg font-semibold">Email queue</h2>
                <p class="mt-3 text-sm">Waiting to send: <strong>{{ $pendingJobs ?? '—' }}</strong>
                    @if ($oldestJobAge) <span class="text-slate-500">(oldest {{ $oldestJobAge }})</span> @endif
                </p>
                @if ($workerStuck)
                    <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">Emails have been waiting over 2 minutes: the queue worker doesn't seem to be running.</p>
                @endif
                @if (config('queue.default') === 'sync')
                    <p class="mt-3 rounded-lg bg-gold-50 px-3 py-2 text-sm text-gold-900">QUEUE_CONNECTION is <code>sync</code>: emails are sent during the teacher's request, which slows posting. Set it to <code>database</code>.</p>
                @endif
            </section>

            {{-- Last run / error --}}
            <section class="card p-6">
                <h2 class="font-serif text-lg font-semibold">Last notify run</h2>
                @if ($lastRun)
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">When</dt><dd>{{ \Illuminate\Support\Carbon::parse($lastRun['at'])->diffForHumans() }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Item</dt><dd class="text-right">{{ ucfirst($lastRun['kind']) }}: {{ $lastRun['title'] }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Section ids</dt><dd>{{ $lastRun['sections'] ?: 'none' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Students notified</dt><dd class="font-semibold">{{ $lastRun['notified'] }}</dd></div>
                    </dl>
                @else
                    <p class="mt-3 text-sm text-slate-500">Nothing posted since the last deploy (or the cache was cleared).</p>
                @endif

                @if ($lastError)
                    <div class="mt-4 rounded-lg bg-red-50 px-3 py-3 text-sm text-red-800">
                        <p class="font-semibold">Last error · {{ \Illuminate\Support\Carbon::parse($lastError['at'])->diffForHumans() }}</p>
                        <p class="mt-1">While notifying “{{ $lastError['title'] }}”</p>
                        <p class="mt-2 font-mono text-xs break-words">{{ $lastError['exception'] }}: {{ $lastError['message'] }}</p>
                        <p class="mt-1 font-mono text-[11px] break-all text-red-600">{{ $lastError['where'] }}</p>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-layouts.app>
