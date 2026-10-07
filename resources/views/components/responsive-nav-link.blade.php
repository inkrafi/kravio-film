@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full border-l-2 border-perak bg-gray-800 py-2 pe-4 ps-3 text-start text-base font-semibold text-white'
            : 'block w-full border-l-2 border-transparent py-2 pe-4 ps-3 text-start text-base font-medium text-gray-400 hover:bg-gray-800 hover:text-gray-100';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
