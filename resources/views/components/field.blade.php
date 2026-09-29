{{-- Form field wrapper: label + control (slot) + hint + validation error --}}
@props(['label', 'name' => null, 'for' => null, 'hint' => null, 'bag' => 'default'])

<div {{ $attributes }}>
    <label for="{{ $for ?? $name }}" class="mb-2 block text-sm font-medium text-ink-800">{{ $label }}</label>
    {{ $slot }}
    @if ($hint)
        <p class="mt-1.5 text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @if ($name && $errors->getBag($bag)->has($name))
        <p class="mt-1.5 text-sm text-red-600">{{ $errors->getBag($bag)->first($name) }}</p>
    @endif
</div>
