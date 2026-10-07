<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-md bg-kredit px-4 py-2 text-sm font-semibold text-white transition hover:brightness-110 disabled:opacity-40']) }}>
    {{ $slot }}
</button>
