<x-layouts.guest title="Access denied">
<div class="flex min-h-full items-center justify-center px-6 py-16">
    <div class="w-full max-w-md text-center">
        <x-brand class="justify-center" />
        <p class="mt-12 font-serif text-7xl font-semibold text-gold-500">403</p>
        <h1 class="mt-4 font-serif text-2xl font-semibold text-ink-900">This area is restricted</h1>
        <p class="mt-3 text-slate-500">{{ $exception->getMessage() ?: 'You do not have access to this page.' }}</p>
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="btn-gold mt-8">
            {{ auth()->check() ? 'Back to my dashboard' : 'Go to sign in' }}
        </a>
    </div>
</div>
</x-layouts.guest>
