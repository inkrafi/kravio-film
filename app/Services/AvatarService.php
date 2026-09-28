<?php

namespace App\Services;

use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Foto profil pengguna.
 *
 * Gambar yang diunggah dipotong jadi persegi dari tengah, diperkecil ke
 * SIZE px, lalu disimpan ulang sebagai WebP. Selain hemat ruang, encode ulang
 * membuang metadata EXIF (mis. lokasi GPS dari foto ponsel).
 */
class AvatarService
{
    public const SIZE = 256;

    private const QUALITY = 85;

    public static function disk(): string
    {
        return (string) config('filesystems.avatar_disk', 'public');
    }

    public function store(User $user, UploadedFile $file): void
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if (! $source instanceof GdImage) {
            throw new RuntimeException('Gambar tidak bisa dibaca.');
        }

        $avatar = $this->squareThumbnail($source);

        ob_start();
        imagewebp($avatar, null, self::QUALITY);
        $contents = (string) ob_get_clean();

        // Nama acak per unggahan, supaya cache browser tidak menampilkan foto lama.
        $path = 'avatars/'.$user->id.'-'.Str::random(12).'.webp';

        Storage::disk(self::disk())->put($path, $contents, 'public');

        $old = $user->avatar_path;

        $user->forceFill(['avatar_path' => $path])->save();

        $this->deleteFile($old);
    }

    public function remove(User $user): void
    {
        $old = $user->avatar_path;

        $user->forceFill(['avatar_path' => null])->save();

        $this->deleteFile($old);
    }

    private function squareThumbnail(GdImage $source): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $thumbnail = imagecreatetruecolor(self::SIZE, self::SIZE);

        // Pertahankan transparansi PNG/WebP.
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);

        imagecopyresampled(
            $thumbnail, $source,
            0, 0,
            intdiv($width - $side, 2), intdiv($height - $side, 2),
            self::SIZE, self::SIZE,
            $side, $side,
        );

        return $thumbnail;
    }

    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk(self::disk())->delete($path);
        }
    }
}
