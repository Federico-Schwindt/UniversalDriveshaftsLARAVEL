<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Swaps Powertrain Industries' watermark for ours on the photos we took from PTI (public/img/pti and
 * public/img/driveshafts), owner request 2026-11-04: "no PTI watermark, our watermark in the middle".
 *
 * 1. PTI's mark is one fixed semi-transparent logo grid stamped on a 500 px canvas from the top-left corner, so it
 *    is subtracted exactly (reverse alpha blending) instead of painted over: the part is never redrawn (owner rule:
 *    no AI re-renders of products). Driveshaft photos carry a faded copy (their background was lightened later),
 *    so its strength is measured per photo. Templates: storage/app/pti/watermark/{set}.png + .json.
 * 2. The clean photo is kept as a master (storage/app/pti/clean/{path}.webp) and the UDS logo is stamped in the
 *    middle of the public copy (settings: storage/app/pti/watermark/brand.json). Changing the brand settings
 *    re-stamps every photo from its master on the next run, never on top of an old stamp.
 *
 * Originals that carried PTI's mark are kept in storage/app/pti/originals/. A photo replaced by a new download
 * (size/time differ from the manifest) is treated as new. Scheduled after the PTI syncs.
 */
class PtiDewatermark extends Command
{
    protected $signature = 'pti:dewatermark {--set=all : parts, shafts, all (= parts + shafts) or uploads (clean only)}
        {--only= : comma-separated file names (or dir/file) to process, or @file with one per line} {--limit=0 : stop after N photos written}
        {--dry-run : only score the photos} {--grey= : override the template grey} {--no-backup : do not keep PTI originals}
        {--out= : review mode: write the results to this folder, nothing in public/ changes}
        {--white= : background level the mark is looked for on (default 247; ~200 for the grey-background shots)}
        {--force : the photos carry the PTI mark even if it cannot be scored (wood, floor or other busy backgrounds)}';
    protected $description = "Replace PTI's watermark on PTI photos with the UDS logo in the middle (no AI, the part is not redrawn)";

    private const SETS = [
        'parts' => ['glob' => 'img/pti/*.{jpg,jpeg,png,JPG,JPEG,PNG}', 'brand' => true],
        'shafts' => ['glob' => 'img/driveshafts/*/*.{jpg,jpeg,png,JPG,JPEG,PNG}', 'brand' => true],
        // photos copied from the old WordPress store: some were PTI photos with the mark (clean only, never stamped)
        'uploads' => ['glob' => 'uploads/wp/*/*/*.{jpg,jpeg,png,JPG,JPEG,PNG}', 'brand' => false],
    ];
    private const S = 500;

    private array $manifest = [];
    private array $opt = [];
    private array $brand = [];
    private string $bv = '';
    private ?\Imagick $logo = null;

    public function handle(): int
    {
        // a 500 px photo is worked on as plain PHP arrays (pixels, background, median…): a transparent one (4 channels)
        // needs ~200 MB, past the CLI's 128 MB default (2026-11-04: 30 transparent part photos stopped the run)
        if ((int) ini_get('memory_limit') !== -1 && $this->bytes((string) ini_get('memory_limit')) < 512 * 1024 * 1024) ini_set('memory_limit', '512M');
        // one writer at a time (the scheduled runs and a manual full pass rewrite the same files and manifest)
        $lock = null;
        if (!$this->option('dry-run') && !$this->option('out')) {
            $lock = \Illuminate\Support\Facades\Cache::lock('pti-dewatermark', 6 * 3600);
            if (!$lock->get()) { $this->warn('Another pti:dewatermark run is busy; skipped.'); return self::SUCCESS; }
        }
        try {
            return $this->process();
        } finally {
            $lock?->release();
        }
    }

    private function process(): int
    {
        $base = storage_path('app/pti/watermark');
        $mfile = "$base/manifest.json";
        $this->manifest = is_file($mfile) ? (json_decode((string) file_get_contents($mfile), true) ?: []) : [];
        $this->loadBrand("$base/brand.json");
        $only = (string) $this->option('only');
        $only = array_filter(array_map('trim', str_starts_with($only, '@') ? (file(substr($only, 1), FILE_IGNORE_NEW_LINES) ?: []) : explode(',', $only)));
        $limit = (int) $this->option('limit');
        $review = (string) $this->option('out');
        $dry = (bool) $this->option('dry-run');
        $sets = $this->option('set') === 'all' ? ['parts', 'shafts'] : [$this->option('set')];
        $written = 0;

        foreach ($sets as $set) {
            if (!isset(self::SETS[$set]) || !is_file("$base/$set.png")) { $this->warn("No template for '$set' (storage/app/pti/watermark/$set.png)."); continue; }
            $cfg = json_decode((string) file_get_contents("$base/$set.json"), true) ?: [];
            $grey = $this->option('grey') !== null ? (float) $this->option('grey') : (float) ($cfg['grey'] ?? 40);
            [$M, $band] = $this->template("$base/$set.png", (float) ($cfg['scale'] ?? 1));
            $minScore = (float) ($cfg['min_score'] ?? 0.2);
            $this->opt = ['white' => (int) ($this->option('white') ?? $cfg['white'] ?? 247), 'fit' => (bool) ($cfg['fit_strength'] ?? false), 'min_k' => (float) ($cfg['min_k'] ?? 0.02), 'polish' => (bool) ($cfg['polish'] ?? true)];
            $brand = self::SETS[$set]['brand'];

            $files = glob(public_path(self::SETS[$set]['glob']), GLOB_BRACE) ?: [];
            sort($files);
            if ($only) $files = array_values(array_filter($files, fn ($f) => in_array(basename($f), $only, true) || in_array(basename(dirname($f)) . '/' . basename($f), $only, true)));
            $stats = ['pti_mark_removed' => 0, 'no_pti_mark' => 0, 'stamped' => 0, 're_stamped' => 0, 'up_to_date' => 0, 'error' => 0];
            $this->info("$set: " . count($files) . " photos" . ($brand ? ', UDS logo ' . json_encode($this->brand) : ', clean only'));
            $bar = $this->output->createProgressBar(count($files));
            foreach ($files as $file) {
                $bar->advance();
                $rel = substr($file, strlen(public_path()) + 1);
                $sig = filesize($file) . ':' . filemtime($file);
                $seen = $this->manifest[$rel] ?? null;
                $legacyClean = is_string($seen) ? $seen === md5_file($file) : (is_array($seen) && ($seen['sig'] ?? '') === $sig && in_array($seen['state'] ?? '', ['cleaned', 'no_mark', 'too_big'], true));
                try {
                    if (!$review && !$dry && is_array($seen) && ($seen['sig'] ?? '') === $sig && ($seen['state'] ?? '') === 'done') {
                        // finished before: only the UDS stamp may need redoing, always from the clean master
                        if (!$brand || ($seen['bv'] ?? '') === $this->bv) { $stats['up_to_date']++; continue; }
                        $master = $this->masterPath($rel);
                        if (!is_file($master)) { $stats['error']++; $this->newLine(); $this->warn("$rel: no clean master to re-stamp from"); continue; }
                        $this->finish($file, $rel, new \Imagick($master), (bool) ($seen['pti'] ?? false), false);
                        $stats['re_stamped']++; $written++;
                    } elseif (!$review && !$dry && $legacyClean) {
                        // cleaned (or found unmarked) by the first run, before stamping existed: the file is the clean master
                        if (!$brand) { $stats['up_to_date']++; continue; }
                        $this->finish($file, $rel, new \Imagick($file), ($seen === null || is_string($seen) || ($seen['state'] ?? '') === 'cleaned'), true);
                        $stats['stamped']++; $written++;
                    } elseif (!$brand && is_array($seen) && ($seen['sig'] ?? '') === $sig) {
                        $stats['up_to_date']++; continue;
                    } else {
                        // new or replaced photo: remove PTI's mark when it carries it, then stamp ours
                        [$state, $clean, $score, $k] = $this->clean($file, $M, $band, $grey, $minScore);
                        $stats[$state === 'cleaned' ? 'pti_mark_removed' : 'no_pti_mark']++;
                        if ($dry) { if ($this->output->isVerbose() && $state === 'cleaned') { $this->newLine(); $this->line(sprintf('%s %s score %.3f strength %.3f', $rel, $state, $score, $k)); } continue; }
                        $img = $clean ?? new \Imagick($file);
                        if ($review) { $this->review($review, $rel, $brand ? $this->stamp(clone $img) : $img); $written++; }
                        elseif ($brand) {
                            if ($state === 'cleaned') $this->backup($file, $rel);
                            $this->finish($file, $rel, $img, $state === 'cleaned', true);
                            $stats['stamped']++; $written++;
                        } elseif ($state === 'cleaned') {
                            $this->backup($file, $rel);
                            $this->write($img, $file);
                            $this->purgeMedia($rel);
                            clearstatcache(true, $file);
                            $this->manifest[$rel] = ['state' => 'done', 'sig' => filesize($file) . ':' . filemtime($file), 'pti' => true];
                            $written++;
                        } else {
                            $this->manifest[$rel] = ['state' => 'done', 'sig' => $sig, 'pti' => false];
                        }
                        if ($this->output->isVerbose()) { $this->newLine(); $this->line(sprintf('%s %s score %.3f strength %.3f', $rel, $state, $score, $k)); }
                    }
                } catch (\Throwable $e) {
                    $stats['error']++; $this->newLine(); $this->warn("$rel: " . $e->getMessage()); continue;
                }
                if ($limit && $written >= $limit) break 2;
                if ($written && $written % 200 === 0 && !$review && !$dry) $this->saveManifest($mfile);
                if (($free = @disk_free_space(public_path())) !== false && $free < 1.2 * 1024 ** 3) {
                    $this->newLine(); $this->error('Less than 1.2 GB free on the disk: stopped.'); break 2;
                }
            }
            $bar->finish(); $this->newLine();
            $this->info("$set: " . json_encode($stats));
        }
        if (!$dry && !$review) $this->saveManifest($mfile);
        return self::SUCCESS;
    }

    /* ---------------- 1. PTI's mark ---------------- */

    /** Template darkening M (0..~64, on white) as a flat 500x500 array, and the grown logo area used by the outline clean-up. */
    private function template(string $png, float $scale): array
    {
        $im = new \Imagick($png);
        $px = $im->exportImagePixels(0, 0, self::S, self::S, 'I', \Imagick::PIXEL_CHAR);
        $M = array_map(fn ($v) => $v / $scale, $px);
        $band = array_fill(0, self::S * self::S, false);
        for ($y = 0; $y < self::S; $y++) for ($x = 0; $x < self::S; $x++) {
            if ($M[$y * self::S + $x] <= 2) continue;
            for ($dy = -1; $dy <= 1; $dy++) for ($dx = -1; $dx <= 1; $dx++) {
                $yy = $y + $dy; $xx = $x + $dx;
                if ($yy >= 0 && $yy < self::S && $xx >= 0 && $xx < self::S) $band[$yy * self::S + $xx] = true;
            }
        }
        return [$M, $band];
    }

    /** ['cleaned', Imagick, score, strength] when the photo carries PTI's mark, ['no_mark', null, score, 0] otherwise. */
    private function clean(string $file, array $M, array $band, float $grey, float $minScore): array
    {
        $im = new \Imagick($file);
        $w = $im->getImageWidth(); $h = $im->getImageHeight();
        if ($w > self::S || $h > self::S) return ['no_mark', null, 0, 0];   // larger than PTI's canvas: never stamped by them at this size
        $alpha = $im->getImageAlphaChannel();
        $map = $alpha ? 'RGBA' : 'RGB'; $n = strlen($map);
        $J = $im->exportImagePixels(0, 0, $w, $h, $map, \Imagick::PIXEL_CHAR);

        // luminance on white (transparent pixels count as white, like the template estimation)
        $L = [];
        for ($i = 0, $c = $w * $h; $i < $c; $i++) {
            $l = ($J[$i * $n] + $J[$i * $n + 1] + $J[$i * $n + 2]) / 3;
            if ($alpha) { $a = $J[$i * $n + 3] / 255; $l = $l * $a + 255 * (1 - $a); }
            $L[$i] = (int) round($l);
        }
        $mx = $this->localMax($L, $w, $h);

        // does this photo carry the template? normalised match of its logo shadows on white areas
        $white = $this->opt['white'];
        $sdm = 0; $smm = 0; $sdd = 0;
        for ($y = 0; $y < $h; $y += 2) for ($x = 0; $x < $w; $x += 2) {
            $i = $y * $w + $x; if ($mx[$i] < $white) continue;
            $d = $mx[$i] - $L[$i]; if ($d < 0 || $d >= 40 * ($white < 247 ? 1 : 3)) continue;
            $m = $M[$y * self::S + $x];
            $sdm += $d * $m; $smm += $m * $m; $sdd += $d * $d;
        }
        $score = $smm && $sdd ? $sdm / sqrt($smm * $sdd) : 0;
        if ($score < $minScore && !$this->option('force')) return ['no_mark', null, $score, 0];
        // photos whose background was lightened after stamping carry a faded copy of the mark: measure how much
        $k = $this->opt['fit'] ? ($smm ? $sdm / $smm : 0) : 1.0;
        if ($k < $this->opt['min_k']) return ['no_mark', null, $score, $k];
        $k = min(1.0, $k);

        // reverse alpha blend: J = a*grey + (1-a)*I  →  I = (J - a*grey) / (1 - a), a = k*M / (255 - grey)
        $out = $J;
        for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) {
            $m0 = $M[$y * self::S + $x]; if ($m0 <= 1.5) continue;
            $a = min(0.9, $m0 * $k / (255 - $grey)); $i = ($y * $w + $x) * $n;
            if ($alpha) {
                // transparent photo: the mark was laid OVER it and added coverage too, so undo the "over" on alpha and colour
                // (over transparent background the mark is the only coverage: it goes back to fully transparent)
                $A = $J[$i + 3] / 255; if ($A <= 0) continue;
                $ai = ($A - $a) / (1 - $a);
                if ($ai <= 0.06) { $out[$i] = $out[$i + 1] = $out[$i + 2] = 255; $out[$i + 3] = 0; continue; }
                for ($c = 0; $c < 3; $c++) $out[$i + $c] = (int) max(0, min(255, round(($J[$i + $c] * $A - $a * $grey) / ((1 - $a) * $ai))));
                $out[$i + 3] = (int) round($ai * 255);
                continue;
            }
            for ($c = 0; $c < 3; $c++) $out[$i + $c] = (int) max(0, min(255, round(($J[$i + $c] - $a * $grey) / (1 - $a))));
        }
        // JPEG ringing on the logo outline. Inside the logo area only: a 3x3 median (full-strength marks), then any pixel
        // that is background (close to the local background tone) takes that tone: the 15x15 median of the cleaned photo.
        // Never pure white: off-white or graded backgrounds would show the logo as a white cut-out (owner, 2026-11-04).
        $cleanIm = $this->fromPixels($w, $h, $map, $out);
        $med = null;
        if ($this->opt['polish']) { $t = clone $cleanIm; $t->statisticImage(\Imagick::STATISTIC_MEDIAN, 3, 3); $med = $t->exportImagePixels(0, 0, $w, $h, $map, \Imagick::PIXEL_CHAR); }
        $t = clone $cleanIm; $t->statisticImage(\Imagick::STATISTIC_MEDIAN, 15, 15); $bg = $t->exportImagePixels(0, 0, $w, $h, $map, \Imagick::PIXEL_CHAR);
        for ($y = 1; $y < $h - 1; $y++) for ($x = 1; $x < $w - 1; $x++) {
            if (!$band[$y * self::S + $x]) continue;
            $i = ($y * $w + $x) * $n;
            if ($med) for ($c = 0; $c < 3; $c++) $out[$i + $c] = $med[$i + $c];
            if (($bg[$i] + $bg[$i + 1] + $bg[$i + 2]) < 3 * 200) continue;   // only on light backgrounds
            $near = true;
            for ($c = 0; $c < 3; $c++) if (abs($out[$i + $c] - $bg[$i + $c]) > 14) { $near = false; break; }
            if ($near) for ($c = 0; $c < 3; $c++) $out[$i + $c] = $bg[$i + $c];
        }
        return ['cleaned', $this->fromPixels($w, $h, $map, $out), $score, $k];
    }

    /** 15x15 local maximum (square dilation) done natively by ImageMagick. */
    private function localMax(array $L, int $w, int $h): array
    {
        $g = $this->fromPixels($w, $h, 'I', $L);
        $g->morphology(\Imagick::MORPHOLOGY_DILATE, 1, \ImagickKernel::fromBuiltIn(\Imagick::KERNEL_SQUARE, '7'));
        return $g->exportImagePixels(0, 0, $w, $h, 'I', \Imagick::PIXEL_CHAR);
    }

    private function fromPixels(int $w, int $h, string $map, array $px): \Imagick
    {
        $im = new \Imagick();
        $im->newImage($w, $h, 'white');
        $im->setImageFormat('png');
        $im->importImagePixels(0, 0, $w, $h, $map, \Imagick::PIXEL_CHAR, $px);
        return $im;
    }

    private function bytes(string $v): int
    {
        $n = (int) $v;
        return match (strtolower(substr(trim($v), -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
    }

    /* ---------------- 2. our mark ---------------- */

    private function loadBrand(string $file): void
    {
        $this->brand = (is_file($file) ? json_decode((string) file_get_contents($file), true) : null) ?: [];
        $this->brand += ['logo' => 'img/uds-logo.png', 'width' => 0.6, 'max_height' => 0.22, 'opacity' => 0.25];
        $logo = public_path($this->brand['logo']);
        $this->bv = substr(md5(json_encode($this->brand) . '|' . (@filemtime($logo) ?: 0)), 0, 10);
        if (is_file($logo)) {
            $this->logo = new \Imagick($logo);
            $this->logo->setImageAlphaChannel(\Imagick::ALPHACHANNEL_SET);
        }
    }

    /** The UDS logo, centred, a share of the photo's width (capped in height), semi-transparent. */
    private function stamp(\Imagick $im): \Imagick
    {
        if (!$this->logo) throw new \RuntimeException('UDS logo not found: ' . $this->brand['logo']);
        $w = $im->getImageWidth(); $h = $im->getImageHeight();
        $ratio = $this->logo->getImageHeight() / max(1, $this->logo->getImageWidth());
        $lw = (int) round($w * (float) $this->brand['width']); $lh = (int) round($lw * $ratio);
        if ($lh > $h * (float) $this->brand['max_height']) { $lh = (int) round($h * (float) $this->brand['max_height']); $lw = (int) round($lh / $ratio); }
        $logo = clone $this->logo;
        $logo->resizeImage(max(1, $lw), max(1, $lh), \Imagick::FILTER_LANCZOS, 1);
        $logo->evaluateImage(\Imagick::EVALUATE_MULTIPLY, (float) $this->brand['opacity'], \Imagick::CHANNEL_ALPHA);
        $im->compositeImage($logo, \Imagick::COMPOSITE_OVER, intdiv($w - $lw, 2), intdiv($h - $lh, 2));
        return $im;
    }

    /** Keep the clean master (unless it is the source), write the stamped public copy, record it. */
    private function finish(string $file, string $rel, \Imagick $clean, bool $ptiRemoved, bool $saveMaster): void
    {
        if ($saveMaster) {
            $master = $this->masterPath($rel);
            if (!is_dir(dirname($master))) @mkdir(dirname($master), 0775, true);
            $m = clone $clean;
            $m->setImageFormat('webp'); $m->setImageCompressionQuality(95); $m->stripImage();
            $m->writeImage($master);
        }
        $this->write($this->stamp(clone $clean), $file);
        $this->purgeMedia($rel);
        clearstatcache(true, $file);
        $this->manifest[$rel] = ['state' => 'done', 'sig' => filesize($file) . ':' . filemtime($file), 'bv' => $this->bv, 'pti' => $ptiRemoved];
    }

    /** Same name, same format: JPEG stays JPEG (q88), PNG stays PNG. Atomic replace. */
    private function write(\Imagick $im, string $file): void
    {
        $png = (bool) preg_match('/\.png$/i', $file);
        if (!$png && $im->getImageAlphaChannel()) { $im->setImageBackgroundColor('white'); $im = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN); }
        $im->setImageFormat($png ? 'png' : 'jpeg');
        if ($png) $im->setOption('png:compression-level', '9');
        else { $im->setImageCompressionQuality(88); $im->setSamplingFactors(['2x2', '1x1', '1x1']); }
        $im->stripImage();
        $tmp = $file . '.dewm.tmp';
        $im->writeImage($tmp);
        if (!@rename($tmp, $file)) { @unlink($tmp); throw new \RuntimeException('could not write the photo'); }
    }

    private function backup(string $file, string $rel): void
    {
        if ($this->option('no-backup')) return;
        $bak = storage_path('app/pti/originals/' . $rel);
        if (!is_dir(dirname($bak))) @mkdir(dirname($bak), 0775, true);
        if (!copy($file, $bak)) throw new \RuntimeException('could not back up the original');
    }

    private function review(string $dir, string $rel, \Imagick $im): void
    {
        @mkdir($dir, 0775, true);
        $im->setImageFormat('png');
        $im->writeImage(rtrim($dir, '/') . '/' . str_replace('/', '__', $rel) . '.png');
    }

    private function masterPath(string $rel): string
    {
        return storage_path('app/pti/clean/' . $rel . '.webp');
    }

    /** Resized WebP copies of the old photo in the media cache (their URLs also change: MediaController::version). */
    private function purgeMedia(string $rel): void
    {
        $key = rtrim(strtr(base64_encode('/' . $rel), '+/', '-_'), '=');
        foreach (\App\Http\Controllers\MediaController::WIDTHS as $wd) @unlink(public_path("media/$wd/$key.webp"));
    }

    private function saveManifest(string $file): void
    {
        if (!is_dir(dirname($file))) @mkdir(dirname($file), 0775, true);
        file_put_contents($file, json_encode($this->manifest, JSON_UNESCAPED_SLASHES));
    }
}
