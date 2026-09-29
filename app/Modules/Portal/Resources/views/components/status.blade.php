@props(['tone' => 'neutral'])

@php
    $tones = [
        'paid' => 'bg-emerald-50 text-emerald-800',
        'late' => 'bg-red-50 text-red-800',
        'pending' => 'bg-amber-50 text-amber-900',
        'neutral' => 'bg-canvas text-muted',
    ];
@endphp

<span {{ $attributes->class(['inline-flex shrink-0 items-center rounded px-2 py-0.5 text-xs font-medium', $tones[$tone] ?? $tones['neutral']]) }}>{{ $slot }}</span>
