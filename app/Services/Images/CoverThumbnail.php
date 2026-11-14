<?php

namespace App\Services\Images;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Превью постера в WebP для карточек, лент и сеток.
 *
 * Ширина 360 px — это самая широкая плитка (сетка студии, 180 px) на экране
 * с двойной плотностью. Кладётся рядом с исходником: `films/abc.jpg` →
 * `films-thumb/abc.webp`. Исходник не трогается — он нужен странице фильма
 * и OG-картинке.
 */
class CoverThumbnail
{
    public const DIRECTORY = 'films-thumb';
    public const WIDTH     = 360;
    public const QUALITY   = 80;

    /**
     * @return string|null путь превью на public-диске; null — сделать не удалось
     *                     (нет файла, нет GD с WebP, битая картинка) — тогда
     *                     фронт показывает исходник, так что это не ошибка.
     */
    public function make(?string $cover): ?string
    {
        if (!$cover || !function_exists('imagewebp'))
            return null;

        $disk = Storage::disk('public');

        try {
            if (!$disk->exists($cover))
                return null;

            $source = @imagecreatefromstring($disk->get($cover));

            if (!$source)
                return null;

            $width  = imagesx($source);
            $height = imagesy($source);

            // Маленькие исходники не растягиваем — только перепаковываем в WebP.
            $targetWidth  = min(self::WIDTH, $width);
            $targetHeight = (int)round($height * $targetWidth / $width);

            $thumb = imagecreatetruecolor($targetWidth, $targetHeight);
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            ob_start();
            imagewebp($thumb, null, self::QUALITY);
            $webp = ob_get_clean();

            imagedestroy($source);
            imagedestroy($thumb);

            $path = self::DIRECTORY . '/' . pathinfo($cover, PATHINFO_FILENAME) . '.webp';
            $disk->put($path, $webp);

            return $path;
        } catch (Throwable $e) {
            Log::warning("Превью постера «{$cover}» не сделано: {$e->getMessage()}");

            return null;
        }
    }
}
