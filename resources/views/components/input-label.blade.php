@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-gray-300']) }}>
    {{ $value ?? $slot }}
</label>
