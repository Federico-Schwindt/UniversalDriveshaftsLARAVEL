<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShippoService
{
    // Origin = the shop (from the original Shippo from-address).
    private const FROM = [
        'name' => 'Universal Driveshafts LLC',
        'street1' => '11611 Prospect Dr Ste 4',
        'city' => 'Odessa', 'state' => 'FL', 'zip' => '33556', 'country' => 'US',
        'phone' => '8136445707',
    ];
    private const DEFAULT_WEIGHT_LB = 15; // typical driveshaft; refined once products carry weight

    public function enabled(): bool
    {
        return (bool) config('services.shippo.enabled') && config('services.shippo.token');
    }

    /**
     * Live carrier rates for a destination. Returns [ ['id','label','cost'], ... ] or [] on any failure.
     * $to needs at least zip + country; state/city optional.
     */
    public function rates(array $to, float $weightLb = 0): array
    {
        if (!$this->enabled()) return [];
        $weightLb = $weightLb > 0 ? $weightLb : self::DEFAULT_WEIGHT_LB;
        try {
            $resp = Http::withHeaders(['Authorization' => 'ShippoToken ' . config('services.shippo.token')])
                ->timeout(10)
                ->post('https://api.goshippo.com/shipments/', [
                    'address_from' => self::FROM,
                    'address_to' => [
                        'name' => $to['name'] ?? 'Customer',
                        'street1' => $to['street1'] ?? 'N/A',
                        'city' => $to['city'] ?? '',
                        'state' => $to['state'] ?? '',
                        'zip' => $to['zip'] ?? '',
                        'country' => $to['country'] ?? 'US',
                    ],
                    'parcels' => [[
                        'length' => '48', 'width' => '8', 'height' => '8', 'distance_unit' => 'in',
                        'weight' => (string) $weightLb, 'mass_unit' => 'lb',
                    ]],
                    'async' => false,
                ]);
            if (!$resp->ok()) return [];
            $rates = $resp->json('rates') ?: [];
            $out = [];
            foreach ($rates as $r) {
                $out[] = [
                    'id' => 'shippo_' . ($r['object_id'] ?? uniqid()),
                    'label' => trim(($r['provider'] ?? '') . ' ' . ($r['servicelevel']['name'] ?? '')) .
                        (!empty($r['estimated_days']) ? ' (' . $r['estimated_days'] . ' day' . ($r['estimated_days'] > 1 ? 's' : '') . ')' : ''),
                    'cost' => round((float) ($r['amount'] ?? 0), 2),
                ];
            }
            usort($out, fn ($a, $b) => $a['cost'] <=> $b['cost']);
            return array_slice($out, 0, 6); // cheapest 6
        } catch (\Throwable $e) {
            Log::warning('Shippo rates failed: ' . $e->getMessage());
            return [];
        }
    }
}
