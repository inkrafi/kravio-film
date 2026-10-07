<?php

namespace App\Support;

/**
 * Skor Kursi Penuh: persentase penonton yang menyukai sebuah judul, yaitu
 * yang memberi rating 7 ke atas dari skala 1-10. Seperti bioskop yang
 * kursinya terisi, makin tinggi persennya makin banyak yang suka.
 */
class KursiPenuh
{
    /** Rating terendah yang dihitung sebagai "suka". */
    public const LIKED_FROM = 7;

    /** Di bawah jumlah ini skornya belum ditampilkan; satu-dua rating bisa menyesatkan. */
    public const MIN_RATINGS = 5;

    /** Jumlah kursi pada deretan visualnya. */
    public const SEATS = 10;

    public static function score(int $liked, int $ratings): ?int
    {
        if ($ratings < self::MIN_RATINGS) {
            return null;
        }

        return (int) round(100 * $liked / $ratings);
    }

    /**
     * Kursi yang terisi pada deretan 10 kursi, mis. 87% menjadi 9 kursi.
     */
    public static function filledSeats(?int $score): int
    {
        return $score === null ? 0 : (int) round($score / (100 / self::SEATS));
    }
}
