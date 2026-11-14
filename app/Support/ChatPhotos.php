<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photos customers attach in the store chat. Each one is re-encoded as a JPEG no larger than 1600 px:
 * that turns phone photos (HEIC included) upright, drops EXIF data such as GPS position, and keeps what
 * is sent to the AI small. Stored privately (storage/app/chat/{conversation}/…) and only served to the
 * customer who sent it and to admins.
 */
class ChatPhotos
{
    public const MAX_PER_MESSAGE = 3;
    /** Photos a customer may send in one chat: each one goes back to the model on every later turn. */
    public const MAX_PER_CONVERSATION = 9;
    /** Largest image decoded (a 12 MP phone photo is well under it). */
    public const MAX_PIXELS = 40_000_000;
    public const MAX_PX = 1600;

    public static function store(UploadedFile $file, int $conversationId): string
    {
        $info = @getimagesize($file->getRealPath());
        if ($info === false || $info[0] * $info[1] > self::MAX_PIXELS) throw new \RuntimeException('That file is not a photo we can read.');
        $path = "chat/$conversationId/" . Str::random(24) . '.jpg';
        $blob = null;
        if (class_exists(\Imagick::class)) {
            $im = new \Imagick();
            $im->readImage($file->getRealPath() . '[0]');   // first frame only (animated / multi-page files)
            $im->autoOrient();
            $im->thumbnailImage(self::MAX_PX, self::MAX_PX, true, false);
            $im->stripImage();
            $im->setImageFormat('jpeg');
            $im->setImageCompressionQuality(82);
            $blob = $im->getImageBlob();
            $im->clear();
        } else {
            $src = imagecreatefromstring((string) file_get_contents($file->getRealPath()));
            if (!$src) throw new \RuntimeException('That file is not a photo we can read.');
            [$w, $h] = [imagesx($src), imagesy($src)];
            $k = min(1, self::MAX_PX / max($w, $h));
            $dst = imagescale($src, (int) round($w * $k), (int) round($h * $k));
            ob_start(); imagejpeg($dst, null, 82); $blob = ob_get_clean();
        }
        Storage::disk('local')->put($path, $blob);
        return $path;
    }

    /** A "data:image/…;base64,…" string from the chat widget: decoded, checked to be a real image, then stored like an upload. */
    public static function storeDataUrl(string $dataUrl, int $conversationId): string
    {
        if (!preg_match('#^data:image/(jpeg|png|webp|gif);base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $m)) throw new \RuntimeException('Not an image.');
        $bytes = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
        $info = $bytes === false || strlen($bytes) < 100 ? false : @getimagesizefromstring($bytes);
        // audit 2026-11-14: a small file can still be a huge image (40k × 40k px); decoding it would exhaust memory
        if ($info === false || $info[0] * $info[1] > self::MAX_PIXELS) throw new \RuntimeException('Not an image.');
        $tmp = tempnam(sys_get_temp_dir(), 'chatimg');
        file_put_contents($tmp, $bytes);
        try {
            return self::store(new UploadedFile($tmp, 'photo.' . $m[1], 'image/' . $m[1], null, true), $conversationId);
        } finally {
            @unlink($tmp);
        }
    }

    public static function base64(string $path): ?string
    {
        return Storage::disk('local')->exists($path) ? base64_encode(Storage::disk('local')->get($path)) : null;
    }
}
