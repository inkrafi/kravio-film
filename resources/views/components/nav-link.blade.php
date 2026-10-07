@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center border-b-2 border-perak px-1 pt-1 text-sm font-semibold text-white'
            : 'inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-gray-400 transition hover:text-gray-100';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
