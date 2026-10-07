<?php

namespace App\Services;

use App\Models\Favorite;
use App\Models\MediaCache;
use App\Models\User;
use GdImage;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Gambar favorit untuk dibagikan: empat poster favorit pemiliknya, nama
 * dengan huruf condensed, bio sebagai baris subtitle kuning, dan wordmark.
 *
 * Gambar disimpan per sidik isi, jadi baru dibuat ulang ketika favorit,
 * urutannya, nama, atau bio berubah.
 */
class FavoriteShareImageService
{
    /** @var array<string, array{0: int, 1: int}> */
    public const FORMATS = [
        'kotak' => [1080, 1080],
        'story' => [1080, 1920],
    ];

    private const DIRECTORY = 'favorit';

    /** Bio panjang dipotong; subtitle sungguhan jarang lebih dari dua baris. */
    private const SUBTITLE_LINES = 2;

    private const COLORS = [
        'layar' => [0x1A, 0x1C, 0x23],
        'kursi' => [0x26, 0x28, 0x33],
        'perak' => [0xE8, 0xE9, 0xED],
        'redup' => [0x90, 0x95, 0xA3],
        'subtitle' => [0xF2, 0xD6, 0x49],
        'hitam' => [0x00, 0x00, 0x00],
    ];

    /**
     * Judul favorit pengguna, urut seperti di profilnya.
     *
     * @return Collection<int, MediaCache>
     */
    public function media(User $user): Collection
    {
        return Favorite::with('media')
            ->where('user_id', $user->id)
            ->orderBy('sort_order')
            ->take(Favorite::MAX_PER_USER)
            ->get()
            ->pluck('media')
            ->filter()
            ->values();
    }

    /**
     * Sidik isi gambar; berubah setiap favorit, urutan, nama, atau bio berubah.
     */
    public function fingerprint(User $user): string
    {
        return substr(sha1(json_encode([
            $this->media($user)->pluck('id')->all(),
            $user->name,
            $user->username,
            $user->bio,
        ])), 0, 16);
    }

    /**
     * Path absolut PNG untuk format ini, dibuat kalau belum ada.
     */
    public function path(User $user, string $format): string
    {
        if (! isset(self::FORMATS[$format])) {
            throw new InvalidArgumentException("Format gambar favorit tidak dikenal: {$format}");
        }

        $disk = Storage::disk('local');
        $file = self::DIRECTORY."/{$user->id}-{$format}-{$this->fingerprint($user)}.png";

        if (! $disk->exists($file)) {
            // Versi lama milik pengguna ini tidak dipakai lagi.
            foreach ($disk->files(self::DIRECTORY) as $old) {
                if (str_starts_with(basename($old), "{$user->id}-{$format}-")) {
                    $disk->delete($old);
                }
            }

            $disk->put($file, $this->render($user, $format));
        }

        return $disk->path($file);
    }

    /**
     * PNG mentah.
     */
    public function render(User $user, string $format): string
    {
        [$width, $height] = self::FORMATS[$format];

        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, $this->color($canvas, 'layar'));

        $media = $this->media($user)->all();
        $posters = $this->posters($media);

        $format === 'story'
            ? $this->drawStory($canvas, $user, $media, $posters)
            : $this->drawSquare($canvas, $user, $media, $posters);

        ob_start();
        imagepng($canvas, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($canvas);

        return $png;
    }

    /**
     * Nama di atas, poster favorit sebaris, bio sebagai subtitle di bawahnya.
     *
     * @param  list<MediaCache>  $media
     * @param  array<int, GdImage>  $posters
     */
    private function drawSquare(GdImage $canvas, User $user, array $media, array $posters): void
    {
        $this->drawLayout($canvas, $user, $media, $posters, [
            'height' => 1080,
            'margin' => 64,
            'gap' => 16,
            // Makin sedikit favorit, makin besar posternya; empat poster tetap muat sebaris.
            'cellWidth' => min(380, intdiv(1080 - 2 * 64 - (count($media) - 1) * 16, max(1, count($media)))),
            'columns' => count($media),
            'heading' => 50,
            'bio' => [22, 820, 36],
            'wordmark' => 30,
        ]);
    }

    /**
     * Nama di atas, poster favorit besar dua per baris, bio dan wordmark di bawah.
     *
     * @param  list<MediaCache>  $media
     * @param  array<int, GdImage>  $posters
     */
    private function drawStory(GdImage $canvas, User $user, array $media, array $posters): void
    {
        $this->drawLayout($canvas, $user, $media, $posters, [
            'height' => 1920,
            'margin' => 72,
            'gap' => 20,
            'cellWidth' => count($media) === 1 ? 600 : 458,
            'columns' => min(2, count($media)),
            'heading' => 56,
            'bio' => [24, 780, 40],
            'wordmark' => 34,
        ]);
    }

    /**
     * Hanya favorit yang ada yang digambar: tiap baris rata tengah, dan blok
     * poster plus subtitle diletakkan di tengah ruang antara judul dan wordmark.
     *
     * @param  list<MediaCache>  $media
     * @param  array<int, GdImage>  $posters
     * @param  array{height: int, margin: int, gap: int, cellWidth: int, columns: int, heading: int, bio: array{0: int, 1: int, 2: int}, wordmark: int}  $layout
     */
    private function drawLayout(GdImage $canvas, User $user, array $media, array $posters, array $layout): void
    {
        ['height' => $height, 'margin' => $margin, 'gap' => $gap, 'cellWidth' => $cellWidth, 'columns' => $columns] = $layout;
        [$bioSize, $bioWidth, $bioLineHeight] = $layout['bio'];

        $cellHeight = (int) round($cellWidth * 1.5);
        $columns = max(1, $columns);
        $rows = (int) ceil(count($media) / $columns);

        $headingEnd = $this->drawHeading($canvas, $user, $margin, $margin + 86, $layout['heading'], 1080 - 2 * $margin);

        $bioLines = blank($user->bio) ? [] : $this->wrap($user->bio, 'semibold', $bioSize, $bioWidth, self::SUBTITLE_LINES);
        $gridHeight = $rows * $cellHeight + ($rows - 1) * $gap;
        $blockHeight = $gridHeight + ($bioLines ? 40 + count($bioLines) * $bioLineHeight : 0);

        $top = $headingEnd + 40;
        $bottom = $height - $margin - 70;
        $gridY = $top + max(0, intdiv($bottom - $top - $blockHeight, 2));

        foreach (array_values($media) as $slot => $item) {
            $row = intdiv($slot, $columns);
            $inRow = min($columns, count($media) - $row * $columns);
            $rowWidth = $inRow * $cellWidth + ($inRow - 1) * $gap;

            $cellX = (int) ((1080 - $rowWidth) / 2) + ($slot % $columns) * ($cellWidth + $gap);
            $cellY = $gridY + $row * ($cellHeight + $gap);

            $this->drawPoster($canvas, $item, $posters[$slot] ?? null, $cellX, $cellY, $cellWidth, $cellHeight);
        }

        $bioY = $gridY + $gridHeight + 40 + $bioLineHeight;
        foreach ($bioLines as $line) {
            $this->subtitle($canvas, $line, $bioSize, 540, $bioY);
            $bioY += $bioLineHeight;
        }

        $this->text($canvas, mb_strtoupper(config('app.name')), 'title', $layout['wordmark'], $margin, $height - $margin, 'perak');
    }

    /**
     * "Favorit {nama}" dengan huruf condensed, lalu @username di bawahnya.
     * Mengembalikan posisi vertikal setelah blok judul.
     */
    private function drawHeading(GdImage $canvas, User $user, int $x, int $y, float $size, int $maxWidth): int
    {
        $lineHeight = (int) round($size * 1.25);

        foreach ($this->wrap('Favorit '.$user->name, 'title', $size, $maxWidth, 2) as $line) {
            $this->text($canvas, $line, 'title', $size, $x, $y, 'perak');
            $y += $lineHeight;
        }

        if ($user->username) {
            $this->text($canvas, '@'.$user->username, 'regular', round($size * 0.38), $x, $y - (int) round($size * 0.35), 'redup');
        }

        return $y;
    }

    /**
     * Poster memenuhi kotaknya; judul tanpa poster tetap terbaca namanya.
     */
    private function drawPoster(GdImage $canvas, MediaCache $media, ?GdImage $poster, int $x, int $y, int $width, int $height): void
    {
        if ($poster) {
            $this->cover($canvas, $poster, $x, $y, $width, $height);

            return;
        }

        imagefilledrectangle($canvas, $x, $y, $x + $width - 1, $y + $height - 1, $this->color($canvas, 'kursi'));

        $lines = $this->wrap($media->title, 'semibold', 16, $width - 28, 3);
        $lineY = $y + $height - 28 - 26 * (count($lines) - 1);

        foreach ($lines as $line) {
            $this->text($canvas, $line, 'semibold', 16, $x + 14, $lineY, 'perak');
            $lineY += 26;
        }
    }

    /**
     * Unduh poster bersamaan; poster yang gagal dibiarkan kosong.
     *
     * @param  list<MediaCache>  $media
     * @return array<int, GdImage>
     */
    private function posters(array $media): array
    {
        $urls = array_filter(array_map(fn (MediaCache $item) => $item->poster_url, $media));

        if ($urls === []) {
            return [];
        }

        try {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (int $slot) => $pool->as((string) $slot)->timeout(8)->get($urls[$slot]),
                array_keys($urls),
            ));
        } catch (Throwable) {
            return [];
        }

        $posters = [];

        foreach ($responses as $slot => $response) {
            if (! $response instanceof Response || $response->failed()) {
                continue;
            }

            $image = @imagecreatefromstring($response->body());

            if ($image !== false) {
                $posters[(int) $slot] = $image;
            }
        }

        return $posters;
    }

    /**
     * Tempel gambar memenuhi kotak tanpa gepeng; kelebihannya dipotong di tengah.
     */
    private function cover(GdImage $canvas, GdImage $image, int $x, int $y, int $width, int $height): void
    {
        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $scale = max($width / $sourceWidth, $height / $sourceHeight);
        $cropWidth = (int) round($width / $scale);
        $cropHeight = (int) round($height / $scale);

        imagecopyresampled(
            $canvas, $image,
            $x, $y,
            (int) (($sourceWidth - $cropWidth) / 2), (int) (($sourceHeight - $cropHeight) / 2),
            $width, $height,
            $cropWidth, $cropHeight,
        );
    }

    /**
     * Kalimat kuning bertepi gelap, rata tengah di titik $centerX.
     */
    private function subtitle(GdImage $canvas, string $line, float $size, float $centerX, int $baseline): void
    {
        $x = (int) round($centerX - $this->width($line, 'semibold', $size) / 2);

        foreach ([[-2, 0], [2, 0], [0, -2], [0, 2], [-1, -1], [1, 1], [-1, 1], [1, -1]] as [$dx, $dy]) {
            $this->text($canvas, $line, 'semibold', $size, $x + $dx, $baseline + $dy, 'hitam');
        }

        $this->text($canvas, $line, 'semibold', $size, $x, $baseline, 'subtitle');
    }

    private function text(GdImage $canvas, string $text, string $font, float $size, float $x, float $baseline, string $color): void
    {
        imagettftext($canvas, $size, 0, (int) round($x), (int) round($baseline), $this->color($canvas, $color), $this->font($font), $text);
    }

    /**
     * Pecah teks per kata supaya muat; baris terakhir dipotong dengan elipsis.
     *
     * @return list<string>
     */
    private function wrap(string $text, string $font, float $size, float $maxWidth, int $maxLines): array
    {
        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/u', trim($text)) as $word) {
            $candidate = $current === '' ? $word : "{$current} {$word}";

            if ($current !== '' && $this->width($candidate, $font, $size) > $maxWidth) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $last = $lines[$maxLines - 1].'…';

            while (mb_strlen($last) > 1 && $this->width($last, $font, $size) > $maxWidth) {
                $last = mb_substr($last, 0, -2).'…';
            }

            $lines[$maxLines - 1] = $last;
        }

        return $lines;
    }

    private function width(string $text, string $font, float $size): float
    {
        $box = imagettfbbox($size, 0, $this->font($font), $text);

        return abs($box[2] - $box[0]);
    }

    private function font(string $name): string
    {
        return resource_path('fonts/'.match ($name) {
            'title' => 'Archivo-ExtraCondensedExtraBold.ttf',
            'semibold' => 'Archivo-SemiBold.ttf',
            default => 'Archivo-Regular.ttf',
        });
    }

    private function color(GdImage $canvas, string $name): int
    {
        [$red, $green, $blue] = self::COLORS[$name];

        return imagecolorallocate($canvas, $red, $green, $blue);
    }
}
