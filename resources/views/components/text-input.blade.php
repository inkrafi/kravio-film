@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-md border-gray-700 bg-layar text-gray-100 placeholder:text-gray-500 focus:border-gray-400 focus:ring-gray-400']) }}>
