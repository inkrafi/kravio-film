<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-md bg-perak px-4 py-2 text-sm font-semibold text-layar transition hover:bg-white disabled:opacity-40']) }}>
    {{ $slot }}
</button>
