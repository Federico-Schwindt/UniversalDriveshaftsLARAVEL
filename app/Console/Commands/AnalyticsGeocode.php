<?php

namespace App\Console\Commands;

use App\Support\GoogleAnalytics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Coordinates for the dashboard globe: each city (and small country) Google Analytics reports is looked up once on
 * OpenStreetMap Nominatim and kept in geo_places. Their policy: one request a second, an identifying User-Agent, no bulk.
 */
class AnalyticsGeocode extends Command
{
    protected $signature = 'analytics:geocode {--limit=40}';
    protected $description = 'Geocode new Google Analytics cities for the admin dashboard globe';

    public function handle(): int
    {
        if (!GoogleAnalytics::configured()) return self::SUCCESS;
        try { GoogleAnalytics::overview(); GoogleAnalytics::live(); } catch (\Throwable $e) { $this->warn($e->getMessage()); }
        $todo = array_slice(GoogleAnalytics::missingPlaces(), 0, (int) $this->option('limit'), true);
        $found = 0;
        foreach ($todo as $key => [$city, $region, $country, $cc]) {
            $q = array_filter(['city' => $city, 'state' => $region !== '(not set)' ? $region : '', 'country' => $country, 'format' => 'json', 'limit' => 1]);
            if ($city === '') $q = ['country' => $country, 'format' => 'json', 'limit' => 1, 'featuretype' => 'country'];
            $r = Http::withHeaders(['User-Agent' => 'UniversalDriveshafts-admin/1.0 (inquiry@universaldriveshafts.com)'])->timeout(20)->get('https://nominatim.openstreetmap.org/search', $q);
            $hit = $r->successful() ? ($r->json()[0] ?? null) : null;
            DB::table('geo_places')->insertOrIgnore(['key' => $key, 'label' => mb_substr(trim("$city, $region, $country", ', '), 0, 190),
                'lat' => $hit['lat'] ?? null, 'lng' => $hit['lon'] ?? null, 'found' => (bool) $hit, 'created_at' => now()]);
            $found += (bool) $hit;
            usleep(1100000);
        }
        $this->info(count($todo) . " place(s) looked up, $found found.");
        return self::SUCCESS;
    }
}
