@props(['href' => null, 'variant' => 'primary', 'type' => 'button'])

@php
    $classes = [
        'inline-flex min-h-11 items-center justify-center gap-2 rounded-md px-4 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--accent) disabled:opacity-60',
        $variant === 'primary' ? 'bg-(--accent) text-(--accent-text) hover:brightness-95' : 'border border-line bg-white text-ink hover:bg-canvas',
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
