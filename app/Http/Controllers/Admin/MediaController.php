<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Image uploads for products, categories and driveshaft specs → /uploads/{folder}/… (served through the WebP cache). */
class MediaController extends Controller
{
    public function upload(Request $r)
    {
        $r->validate(['file' => 'required|file|mimes:jpg,jpeg,png,webp|max:12288', 'folder' => 'nullable|in:products,categories,driveshafts']);
        $folder = $r->input('folder', 'products');
        $f = $r->file('file');
        $info = @getimagesize($f->getRealPath());
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return response()->json(['error' => 'That file is not a JPG, PNG or WebP image.'], 422);
        // extension comes from the decoded image type, never from the uploaded file name
        // GD needs ~5 bytes a pixel: a 12 MB file can still decode to a 100+ MP bitmap and take the worker down
        if ((int) $info[0] * (int) $info[1] > 40_000_000) return response()->json(['error' => 'That image is too large (' . round($info[0] * $info[1] / 1e6) . ' MP). Resize it under 40 megapixels and try again.'], 422);
        $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2]];
        $name = Str::slug(pathinfo($f->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'image';
        $name = substr($name, 0, 60) . '-' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;
        $dir = public_path('uploads/' . $folder);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $path = $dir . '/' . $name;
        // normalise big uploads to ≤1600px JPEG/PNG so the site never serves a 6 MB phone photo
        if (!$this->shrink($f->getRealPath(), $path, $info[2])) return response()->json(['error' => 'That image could not be read. Re-save it as JPG or PNG and try again.'], 422);
        return response()->json(['url' => '/uploads/' . $folder . '/' . $name]);
    }

    /** Re-encode (drops EXIF/GPS); false when GD cannot decode it — never store the raw upload. */
    private function shrink(string $src, string $dst, int $type): bool
    {
        $max = 1600;
        $img = match ($type) { IMAGETYPE_JPEG => @imagecreatefromjpeg($src), IMAGETYPE_PNG => @imagecreatefrompng($src), IMAGETYPE_WEBP => @imagecreatefromwebp($src), default => null };
        if (!$img) return false;
        // phone photos are stored sideways with an EXIF rotate flag; re-encoding drops the flag, so apply it first
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $o = (int) (@exif_read_data($src)['Orientation'] ?? 1);
            if (in_array($o, [2, 4, 5, 7], true)) imageflip($img, IMG_FLIP_HORIZONTAL);
            $deg = match ($o) { 3, 4 => 180, 6, 7 => -90, 5, 8 => 90, default => 0 };   // imagerotate turns counter-clockwise
            if ($deg) { $rot = imagerotate($img, $deg, 0); if ($rot) { imagedestroy($img); $img = $rot; } }
        }
        $w = imagesx($img); $h = imagesy($img);
        if ($w > $max || $h > $max) {
            $s = min($max / $w, $max / $h); $nw = (int) round($w * $s); $nh = (int) round($h * $s);
            $out = imagecreatetruecolor($nw, $nh);
            if ($type !== IMAGETYPE_JPEG) { imagealphablending($out, false); imagesavealpha($out, true); }
            imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h); imagedestroy($img); $img = $out;
        }
        $ext = strtolower(pathinfo($dst, PATHINFO_EXTENSION));
        $ok = $ext === 'png' ? imagepng($img, $dst, 7) : ($ext === 'webp' ? imagewebp($img, $dst, 86) : imagejpeg($img, $dst, 88));
        imagedestroy($img);
        return (bool) $ok;
    }
}
