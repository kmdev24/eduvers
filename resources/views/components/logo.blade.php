{{-- Movers Institute logo badge (public/images/logo.jpg), with a gold "EV" fallback. --}}
@props(['class' => 'size-11'])

@if (file_exists(public_path('images/logo.jpg')))
    <img src="{{ asset('images/logo.jpg') }}" alt="Movers Institute of Technology and Education"
         {{ $attributes->merge(['class' => $class.' shrink-0 rounded-full bg-white object-cover ring-2 ring-gold-500/70 shadow-gold']) }}>
@else
    <span {{ $attributes->merge(['class' => $class.' relative flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-gold-gradient shadow-gold']) }}>
        <span class="font-serif text-lg font-bold text-ink-900">EV</span>
    </span>
@endif
