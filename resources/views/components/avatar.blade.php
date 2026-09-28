@props(['user', 'size' => 'h-9 w-9 text-sm'])

{{-- Foto profil, atau huruf awal nama kalau belum ada foto. --}}
<span {{ $attributes->class([
    'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-200 font-semibold text-gray-500 dark:bg-gray-700 dark:text-gray-300',
    $size,
]) }}>
    @if ($user->avatar_url)
        <img src="{{ $user->avatar_url }}" alt="Foto {{ $user->name }}" loading="lazy" class="h-full w-full object-cover">
    @else
        <span aria-hidden="true">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
    @endif
</span>
