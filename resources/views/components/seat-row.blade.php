@props(['score' => null, 'animate' => false])

@php
    $filled = \App\Support\KursiPenuh::filledSeats($score);
@endphp

{{-- Deretan 10 kursi bioskop; kursi kuning sebanyak skor Kursi Penuh. --}}
<div {{ $attributes->merge(['class' => 'flex gap-1']) }} role="img"
     aria-label="{{ $score === null ? 'Skor Kursi Penuh belum tersedia' : "Kursi Penuh {$score}%: {$filled} dari ".\App\Support\KursiPenuh::SEATS.' kursi terisi' }}">
    @for ($seat = 1; $seat <= \App\Support\KursiPenuh::SEATS; $seat++)
        <svg viewBox="0 0 20 22" aria-hidden="true"
             @class([
                 'h-[1.4em] w-auto fill-current',
                 'text-subtitle' => $seat <= $filled,
                 'text-gray-700' => $seat > $filled,
                 'seat-fill' => $animate && $seat <= $filled,
             ])
             @if ($animate && $seat <= $filled) style="animation-delay: {{ 300 + $seat * 90 }}ms" @endif>
            <path d="M5 1h10a3 3 0 0 1 3 3v8H2V4a3 3 0 0 1 3-3Z" />
            <path d="M0 13h20v4H0z" />
            <path d="M2 17h3v5H2zM15 17h3v5h-3z" />
        </svg>
    @endfor
</div>
