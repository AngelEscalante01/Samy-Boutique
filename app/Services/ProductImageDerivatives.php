<?php

namespace App\Services;

use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductImageDerivatives
{
    public function create(ProductImage $image, UploadedFile $upload, array $kinds = ['optimized', 'thumbnail']): array
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp') || ! function_exists('exif_read_data')) {
            throw new \RuntimeException('PHP GD/WebP y EXIF son necesarios para crear imágenes optimizadas.');
        }
        $dimensions = getimagesize($upload->getRealPath());
        if (! $dimensions || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] * $dimensions[1] > 32000000) {
            throw new \RuntimeException('La imagen supera el límite seguro de dimensiones.');
        }
        $source = imagecreatefromstring(file_get_contents($upload->getRealPath()));
        if (! $source) throw new \RuntimeException('No se pudo decodificar la imagen.');

        $written = [];
        try {
            $orientation = $this->orientation($upload->getRealPath());
            $source = $this->orient($source, $orientation);
            $dimensions = [imagesx($source), imagesy($source)];
            foreach (['optimized' => 1600, 'thumbnail' => 480] as $kind => $maxEdge) {
                if (! in_array($kind, $kinds, true)) continue;
                $scale = min(1.0, $maxEdge / max($dimensions[0], $dimensions[1]));
                $width = max(1, (int) round($dimensions[0] * $scale));
                $height = max(1, (int) round($dimensions[1] * $scale));
                $target = imagecreatetruecolor($width, $height);
                imagealphablending($target, false);
                imagesavealpha($target, true);
                imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
                imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $dimensions[0], $dimensions[1]);
                $temp = tempnam(sys_get_temp_dir(), 'samy-image-');
                try {
                    if (! imagewebp($target, $temp, $kind === 'thumbnail' ? 78 : 84)) {
                        throw new \RuntimeException('No se pudo codificar la imagen WebP.');
                    }
                    $path = dirname($image->path).'/'.$image->generation.'-'.$kind.'-exif'.$orientation.'.webp';
                    $stream = fopen($temp, 'rb');
                    try {
                        if (! Storage::disk($image->disk)->put($path, $stream, ['ContentType' => 'image/webp'])) {
                            throw new \RuntimeException('No se pudo cargar una versión optimizada.');
                        }
                    } finally {
                        fclose($stream);
                    }
                    $written[] = $path;
                    $bytes = filesize($temp);
                    $sha = hash_file('sha256', $temp);
                    app(ProductImageStorage::class)->verify($image->disk, $path, $sha, $bytes);
                    $image->derivatives()->create([
                        'kind' => $kind, 'disk' => $image->disk, 'path' => $path,
                        'generation' => $image->generation, 'sha256' => $sha,
                        'bytes' => $bytes, 'content_type' => 'image/webp',
                    ]);
                } finally {
                    @unlink($temp);
                    imagedestroy($target);
                }
            }
        } catch (\Throwable $error) {
            foreach ($written as $path) Storage::disk($image->disk)->delete($path);
            throw $error;
        } finally {
            imagedestroy($source);
        }

        return $written;
    }

    public function orientation(string $path): int
    {
        if (! function_exists('exif_read_data')) {
            throw new \RuntimeException('La extensión PHP EXIF es necesaria.');
        }
        if (@exif_imagetype($path) !== IMAGETYPE_JPEG) return 1;
        $data = @exif_read_data($path, 'IFD0', true, false);
        $orientation = (int) ($data['IFD0']['Orientation'] ?? 1);
        return $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
    }

    private function orient(\GdImage $source, int $orientation): \GdImage
    {
        if ($orientation === 2) imageflip($source, IMG_FLIP_HORIZONTAL);
        if ($orientation === 4) imageflip($source, IMG_FLIP_VERTICAL);
        $angle = match ($orientation) {
            3 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };
        if ($angle !== 0) {
            $rotated = imagerotate($source, $angle, imagecolorallocatealpha($source, 0, 0, 0, 127));
            if (! $rotated) throw new \RuntimeException('No se pudo orientar la imagen.');
            imagedestroy($source);
            $source = $rotated;
        }
        if (in_array($orientation, [5, 7], true)) imageflip($source, IMG_FLIP_HORIZONTAL);
        return $source;
    }
}
