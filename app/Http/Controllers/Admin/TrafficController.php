<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\GoogleAnalytics;

/** Google Analytics for the dashboard card: the 28-day picture and who is on the site now, with globe points. */
class TrafficController extends Controller
{
    public function data()
    {
        if (!GoogleAnalytics::configured()) return response()->json(['error' => 'Google Analytics is not connected (no service-account key).'], 503);
        try {
            $o = GoogleAnalytics::overview();
            return response()->json([
                'kpi' => $o['kpi'], 'channels' => $o['channels'], 'countries' => array_slice($o['countries'], 0, 8), 'pages' => $o['pages'],
                'devices' => $o['devices'], 'daily' => $o['daily'], 'points' => GoogleAnalytics::points($o['cities'], true), 'at' => $o['at'],
            ] + $this->liveData());
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    public function live()
    {
        try { return response()->json($this->liveData()); }
        catch (\Throwable $e) { return response()->json(['error' => $e->getMessage()], 502); }
    }

    private function liveData(): array
    {
        $l = GoogleAnalytics::live();
        return ['live' => ['total' => $l['total'], 'points' => GoogleAnalytics::points($l['cities'], false), 'pages' => $l['pages'], 'devices' => $l['devices'], 'at' => $l['at']]];
    }
}
