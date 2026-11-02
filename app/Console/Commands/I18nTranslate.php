<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Machine-translates the storefront into another language with Gemini, from the English source
 * (the JSON keys / English views), using the hand-checked Spanish as a second reference.
 *
 *   php artisan i18n:translate fr              every string missing from lang/i18n/<area>/fr.json
 *   php artisan i18n:translate fr --views      also the long-form pages (i18n-content/*, legal pages, guides)
 *
 * Safe to re-run: only missing keys / missing files are translated. :placeholders, HTML tags, part
 * numbers, series (1310, 1350…), units and brand names must come back unchanged, or the line is retried.
 */
class I18nTranslate extends Command
{
    protected $signature = 'i18n:translate {lang} {--views} {--area=} {--batch=60} {--force}';
    protected $description = 'Machine-translate storefront strings (and optionally pages) into another language';

    private const NAMES = ['fr' => 'French (France, formal "vous")', 'es' => 'Spanish (Latin America)', 'pt' => 'Portuguese (Brazil)', 'de' => 'German'];

    public function handle(): int
    {
        $lang = strtolower($this->argument('lang'));
        if (!isset(self::NAMES[$lang]) || $lang === 'es') { $this->error('Unsupported language.'); return 1; }
        foreach (glob(lang_path('i18n/*'), GLOB_ONLYDIR) as $dir) {
            $area = basename($dir);
            if ($this->option('area') && $this->option('area') !== $area) continue;
            $this->strings($lang, $dir);
        }
        $this->phpFiles($lang);
        $this->glossary($lang);
        if ($this->option('views')) $this->views($lang);
        return 0;
    }

    private function strings(string $lang, string $dir): void
    {
        $es = json_decode((string) @file_get_contents("$dir/es.json"), true) ?: [];
        $file = "$dir/$lang.json";
        $have = $this->option('force') ? [] : (json_decode((string) @file_get_contents($file), true) ?: []);
        $todo = array_values(array_diff(array_keys($es), array_keys($have)));
        $this->line(basename($dir) . ': ' . count($todo) . ' to translate');
        foreach (array_chunk($todo, (int) $this->option('batch')) as $i => $chunk) {
            $items = [];
            foreach ($chunk as $k => $en) $items[] = ['id' => $k, 'en' => $en, 'es' => $es[$en]];
            $out = $this->ask_(
                "Translate each item's \"en\" text into " . self::NAMES[$lang] . " for an e-commerce site that sells driveshaft parts (u-joints, yokes, center support bearings, tubing) in the USA. "
                . "The \"es\" text is an approved Spanish translation; use it to understand the meaning, but translate from English. "
                . "Keep EXACTLY unchanged: every :placeholder (like :name, :qty), HTML tags and attributes, entities (&amp;), Markdown, URLs, e-mail addresses, phone numbers, part numbers, series numbers (1310, 1350, 1410…), measurements and units (in, lb, mm), and brand names (Spicer, Dana, Neapco, UPS, FedEx, EasyPost, PTI, Universal Driveshafts, UDS). "
                . "Use the standard French trade terms: driveshaft = arbre de transmission, u-joint = croisillon, yoke = fourche, slip yoke = fourche coulissante, weld yoke = fourche à souder, flange yoke = fourche à bride, center support bearing = palier central, tubing = tube. "
                . "Keep the same tone and length; short UI labels stay short. "
                . 'Return JSON: {"t":[{"id":<same id>,"text":"<translation>"}]} with one entry per item.',
                json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            $got = [];
            foreach ((array) ($out['t'] ?? []) as $t) if (isset($t['id'], $t['text']) && isset($chunk[$t['id']])) $got[$chunk[$t['id']]] = (string) $t['text'];
            foreach ($got as $en => $tr) {
                if (!$this->sameTokens($en, $tr)) { unset($got[$en]); continue; }
                $have[$en] = $tr;
            }
            $this->line('  batch ' . ($i + 1) . ': ' . count($got) . '/' . count($chunk));
            ksort($have);
            usleep(4_000_000);
            file_put_contents($file, json_encode($have, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        }
    }

    /** Laravel's own messages (validation, auth, pagination, passwords): lang/es/*.php → lang/<lang>/*.php */
    private function phpFiles(string $lang): void
    {
        @mkdir(lang_path($lang), 0775, true);
        foreach (glob(lang_path('es/*.php')) as $f) {
            $dest = lang_path($lang . '/' . basename($f));
            if (is_file($dest) && !$this->option('force')) continue;
            $out = $this->ask_('Translate the string values of this Laravel PHP language array (Spanish) into ' . self::NAMES[$lang] . '. Keep every key and every :placeholder unchanged. Return JSON: {"t": <the same nested structure with translated values>}.',
                json_encode(include $f, JSON_UNESCAPED_UNICODE));
            if (!is_array($out['t'] ?? null)) { $this->warn(basename($f) . ' failed'); continue; }
            file_put_contents($dest, "<?php\n\n// machine-translated by i18n:translate from lang/es/" . basename($f) . "\nreturn " . var_export($out['t'], true) . ";\n");
            $this->line('lang/' . $lang . '/' . basename($f));
        }
    }

    /** Long pages: one Blade file per page, translated whole (Blade directives and HTML kept). */
    private function views(string $lang): void
    {
        $jobs = [];
        foreach (glob(resource_path('views/i18n-content/{,guides/}*.blade.php'), GLOB_BRACE) as $f) {
            $rel = substr($f, strlen(resource_path('views/i18n-content/')));
            $jobs[$f] = resource_path("views/i18n-content/$lang/$rel");
        }
        foreach (glob(resource_path('views/i18n-content/legal-en/*.blade.php')) as $f) $jobs[$f] = resource_path("views/i18n-content/$lang/legal/" . basename($f));
        foreach ($jobs as $src => $dest) {
            if (is_file($dest) && !$this->option('force')) continue;
            $out = $this->ask_(
                'Translate this Blade/HTML fragment into ' . self::NAMES[$lang] . ' for a US driveshaft parts shop. Translate only human-readable text (including alt, title, aria-label and placeholder attributes). '
                . 'Keep every HTML tag, attribute name, class, href, Blade directive (@if, {{ }}, {!! !!}, @include…), PHP code, URL, e-mail, phone number, part number, series number, measurement and brand name exactly as is. '
                . 'Trade terms: driveshaft = arbre de transmission, u-joint = croisillon, yoke = fourche, center support bearing = palier central. '
                . 'Return JSON: {"t":"<the whole translated fragment>"}.',
                (string) file_get_contents($src), 32000
            );
            $t = (string) ($out['t'] ?? '');
            if (strlen($t) < strlen((string) file_get_contents($src)) * 0.6 || substr_count($t, '<') < substr_count((string) file_get_contents($src), '<') * 0.9) { $this->warn("$src: translation looks incomplete, skipped"); continue; }
            @mkdir(dirname($dest), 0775, true);
            file_put_contents($dest, $t);
            $this->line('view ' . substr($dest, strlen(resource_path('views/'))));
        }
        // guide titles / summaries / FAQs (the PHP overlays the guide layout reads)
        foreach (['guides'] as $name) {
            $src = lang_path("i18n/content/$name-es.php"); $dest = lang_path("i18n/content/$name-$lang.php");
            if (!is_file($src) || (is_file($dest) && !$this->option('force'))) continue;
            // one guide at a time: the whole file is too long for one answer
            $all = [];
            foreach ((include $src) as $slug => $entry) {
                $out = $this->ask_('Translate every string value of this PHP array (Spanish) into ' . self::NAMES[$lang] . '. Keep keys, HTML and numbers unchanged. Return JSON: {"t": <same structure>}.', json_encode($entry, JSON_UNESCAPED_UNICODE), 16000);
                if (!is_array($out['t'] ?? null)) { $this->warn("$name/$slug failed"); continue 2; }
                $all[$slug] = $out['t'];
                usleep(4_000_000);
            }
            file_put_contents($dest, "<?php\n\n// machine-translated by i18n:translate from $name-es.php\nreturn " . var_export($all, true) . ";\n");
            $this->line("lang/i18n/content/$name-$lang.php");
        }
    }

    /** Product-title glossary (English driveline words => this language), read by App\Support\PartGlossary. */
    private function glossary(string $lang): void
    {
        $dest = lang_path("i18n/catalog/glossary-$lang.php");
        if (is_file($dest) && !$this->option('force')) return;
        $terms = \App\Support\PartGlossary::sourceTerms(); $out = [];
        foreach (array_chunk($terms, 120, true) as $chunk) {
            $r = $this->ask_('These are driveline part words used in product titles, with an approved Spanish translation. Give the ' . self::NAMES[$lang] . ' trade term for each English phrase (lower-case words stay lower-case, Capitalised stay Capitalised; keep it short like the Spanish). Return JSON: {"t":{"<english>":"<translation>", …}} with every key.',
                json_encode($chunk, JSON_UNESCAPED_UNICODE));
            foreach ((array) ($r['t'] ?? []) as $en => $tr) if (isset($chunk[$en]) && is_string($tr) && $tr !== '') $out[$en] = $tr;
        }
        if (count($out) < count($terms) * 0.9) { $this->warn('glossary: only ' . count($out) . '/' . count($terms) . ', not written'); return; }
        file_put_contents($dest, "<?php\n\n// machine-translated by i18n:translate from App\\Support\\PartGlossary::TERMS\nreturn " . var_export($out, true) . ";\n");
        $this->line("glossary: " . count($out) . ' terms');
    }

    /** :placeholders and HTML tags must survive the translation. */
    private function sameTokens(string $a, string $b): bool
    {
        $tok = function (string $s) { preg_match_all('/:[a-z_]+\b|<\/?[a-z0-9]+/i', $s, $m); $m = $m[0]; sort($m); return $m; };
        return $tok($a) === $tok($b) && trim($b) !== '';
    }

    private function ask_(string $instruction, string $payload, int $maxOut = 16000): array
    {
        $body = ['systemInstruction' => ['parts' => [['text' => $instruction]]], 'contents' => [['role' => 'user', 'parts' => [['text' => $payload]]]],
            'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => $maxOut, 'responseMimeType' => 'application/json']];
        foreach ([0, 1, 2, 3, 4] as $try) {
            foreach (['gemini-3.5-flash', 'gemini-flash-latest', 'gemini-flash-lite-latest'] as $m) {
                try {
                    $r = Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])->timeout(180)
                        ->post("https://generativelanguage.googleapis.com/v1beta/models/$m:generateContent", $body);
                } catch (\Throwable $e) { continue; }
                if (!$r->successful()) { Log::info("i18n:translate $m {$r->status()}"); continue; }
                $text = collect($r->json('candidates.0.content.parts') ?? [])->pluck('text')->implode('');
                $j = json_decode($text, true);
                if (is_array($j)) return $j;
            }
            sleep(10 * ($try + 1));
        }
        $this->warn('  no answer from Gemini');
        return [];
    }
}
