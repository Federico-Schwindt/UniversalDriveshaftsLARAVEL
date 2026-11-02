<?php

namespace App\Services\Chatbot;

use App\Services\CartService;
use App\Services\CatalogService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * What the assistant knows about the shop, read from the shop's own pages rather than
 * written by hand, so it never drifts from what the site actually says. Products are not
 * in here — there are thousands and their price and stock move — the assistant looks
 * those up live through its tools.
 */
class KnowledgeBase
{
    private const FILE = 'chatbot/knowledge.md';

    /** Pages whose words the assistant may quote. Path => what the page is. */
    private const PAGES = [
        '/about-us' => 'About Universal Driveshafts',
        '/services' => 'Services: custom builds, repair, balancing, conversions',
        '/heavy-duty-driveshafts' => 'Heavy-duty driveshafts',
        '/industrial-driveshafts' => 'Industrial driveshafts',
        '/custom-off-road-driveshafts' => 'Custom off-road driveshafts',
        '/pto-agriculture' => 'PTO and agriculture',
        '/premade-driveshafts' => 'Pre-made driveshafts',
        '/wholesale' => 'Wholesale and trade accounts',
        '/contact' => 'Contact',
        '/return-policy' => 'Returns, refunds and limited warranty',
        '/terms' => 'Terms of use and conditions of sale',
        '/shop' => 'Parts catalog overview',
    ];

    private const MAX_PAGE_CHARS = 9000;

    public function text(): string
    {
        if (!Storage::disk('local')->exists(self::FILE)) $this->build();
        return (string) Storage::disk('local')->get(self::FILE);
    }

    /** The "Shop facts" section only (address, phones, hours, categories): small enough for every prompt. */
    public function factsText(): string
    {
        $doc = $this->text();
        $parts = preg_split('/\n(?=## )/', $doc);
        return trim($parts[0] ?? '');
    }

    /**
     * Sections of the shop's own pages that best match a question, for providers whose prompt can't carry the
     * whole knowledge file (the shop_info tool). Scored by how many of the question's words each section holds.
     */
    public function search(string $q, int $take = 2, int $maxChars = 2500): array
    {
        $words = array_values(array_unique(array_filter(preg_split('/[^a-z0-9]+/', strtolower($q)), fn ($w) => strlen($w) >= 3)));
        $scored = [];
        foreach (preg_split('/\n(?=## )/', $this->text()) as $sec) {
            $l = strtolower($sec); $score = 0;
            foreach ($words as $w) $score += min(5, substr_count($l, $w)) + (str_contains(strtolower(strtok($sec, "\n")), $w) ? 5 : 0);
            if ($score) $scored[] = [$score, $sec];
        }
        usort($scored, fn ($a, $b) => $b[0] <=> $a[0]);
        return array_map(fn ($x) => mb_substr($x[1], 0, $maxChars), array_slice($scored, 0, $take));
    }

    /** Rebuild from the live pages. Returns [chars, pages read]. */
    public function build(): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $out = [$this->facts()];
        $read = 0;
        foreach (self::PAGES as $path => $title) {
            try {
                $r = Http::timeout(20)->get($base . $path);
                if (!$r->successful()) continue;
                $text = $this->mainText($r->body());
                if (mb_strlen($text) < 200) continue;   // a 404 or a redirect stub teaches nothing
                $out[] = "## {$title} ({$path})\n\n" . mb_substr($text, 0, self::MAX_PAGE_CHARS);
                $read++;
            } catch (\Throwable $e) {
                Log::warning("chatbot knowledge: {$path} — " . $e->getMessage());
            }
        }
        $doc = implode("\n\n---\n\n", $out);
        Storage::disk('local')->put(self::FILE, $doc);
        return [mb_strlen($doc), $read];
    }

    /** Facts that live in config rather than on a page. */
    private function facts(): string
    {
        $u = config('uds');
        $cats = [];
        try {
            foreach (app(CatalogService::class)->groups() as $g) $cats[] = '- ' . ($g['name'] ?? $g['slug'] ?? '');
        } catch (\Throwable $e) {}

        return trim(implode("\n", array_filter([
            '## Shop facts',
            // the shop's location is private (owner, 2026-10-24): never give the city or street; pickup details are shown at checkout
            'Company: Universal Driveshafts LLC (UDS), Florida, USA. Website: universaldriveshafts.com. Never tell the customer the shop\'s city or street address, even if asked; say we are in Florida and that the pickup address is shown at checkout when they choose local pickup.',
            'Hours: Monday to Friday 8:00 am to 5:00 pm, Saturday 9:00 am to 2:00 pm (Eastern).',
            'Parts phone: ' . ($u['phone_parts'] ?? '') . '. OEM / custom driveshaft phone: ' . ($u['phone_oem'] ?? '') . '. E-mail: ' . ($u['email'] ?? '') . '.',
            !empty($u['pickup']['label']) ? 'Local pickup: free, ' . ($u['pickup']['hours'] ?? 'Monday to Friday, 3 to 5 pm') . '. Choose it at checkout; the address is shown there.' : null,
            'Free shipping: U.S. orders over $' . number_format(CartService::freeMin()) . ' ship free by ground, but tubing and complete driveshafts never count toward it and never ship free. A palletised (freight) order never ships free.',
            'Parcel shipping: boxes ship by UPS or FedEx (ground and air) at live rates shown at checkout. The shop does not ship USPS.',
            'Freight (LTL) shipping: orders of five or more tubes or complete driveshafts, 100 lb or more, or any piece longer than 108 inches ship strapped to a pallet by LTL truck freight. Freight is NOT shipped by UPS or FedEx parcel service and is not "UPS/FedEx freight": it is quoted live at checkout from ' . \App\Services\WarpFreightService::carrierSummary() . '. At checkout the customer picks the delivery type (business with a loading dock, business without a dock so the truck brings a liftgate, or a home), which changes the price. The carrier calls ahead to schedule; someone must be there to sign and should note any visible damage on the delivery receipt. Freight never ships free and free-shipping coupons do not cover it.',
            'Online tools: /online-builder (build a driveshaft to your measurements), /driveshaft-designer (draw and dimension a shaft), the vehicle finder (driveshafts by year, make and model).',
            $cats ? "Catalog categories:\n" . implode("\n", $cats) : null,
        ])));
    }

    private function mainText(string $html): string
    {
        if (preg_match('/<main[^>]*>(.*)<\/main>/is', $html, $m)) $html = $m[1];
        $html = preg_replace('/<(script|style|svg|noscript|form)[^>]*>.*?<\/\1>/is', ' ', $html);
        $html = preg_replace('/<\/(p|h[1-6]|li|tr|div|section)>/i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        return trim(preg_replace('/\n\s*\n+/', "\n", $text));
    }
}
