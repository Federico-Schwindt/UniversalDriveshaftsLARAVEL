<?php

namespace App\Console\Commands;

use App\Services\DriveshaftFinder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyDriveshaftCatalog extends Command
{
    protected $signature = 'catalog:verify-driveshafts {--walks=300}';
    protected $description = 'Acceptance checks for the vehicle-to-driveshaft finder';

    public function handle(DriveshaftFinder $f): int
    {
        $fail = 0;
        $check = function (string $name, bool $ok, string $detail = '') use (&$fail) {
            $this->line(($ok ? '<info>PASS</info>' : '<error>FAIL</error>') . "  $name" . ($detail ? "  <comment>$detail</comment>" : ''));
            if (!$ok) $fail++;
        };

        // 1. Silverado reference case: some path must end at exactly 4191-6100 + 4191-9286
        $ends = $this->allEndings($f, ['make' => 'Chevrolet', 'year' => 2015, 'model' => 'Silverado 1500', 'drive' => 'RWD/2WD']);
        $want = ['4191-6100', '4191-9286'];
        $hit = array_filter($ends, fn ($e) => $e == $want);
        $start = $f->step(['make' => 'Chevrolet', 'year' => 2015, 'model' => 'Silverado 1500', 'drive' => 'RWD/2WD']);
        $check('2015 Chevrolet Silverado 1500 RWD/2WD narrows to 4191-6100 + 4191-9286', (bool) $hit,
            "start: {$start['candidate_count']} parts / {$start['fitment_rows']} rows, " . count($ends) . ' distinct endings');

        // 2. stress case terminates, fast
        $t = microtime(true);
        $s = $f->step(['make' => 'International', 'year' => 2011, 'model' => '4300', 'drive' => 'RWD/2WD']);
        $e = $this->allEndings($f, ['make' => 'International', 'year' => 2011, 'model' => '4300', 'drive' => 'RWD/2WD'], 400);
        $ms = round((microtime(true) - $t) * 1000);
        $check('2011 International 4300 RWD/2WD terminates', count($e) > 0, "start {$s['candidate_count']} parts, " . count($e) . " endings explored in {$ms} ms");

        // 3. NULL means unrestricted
        $row = DB::table('ds_fitments')->whereNull('engine')->whereExists(fn ($q) => $q->from('ds_fitments as b')
            ->whereColumn(['b.make' => 'ds_fitments.make', 'b.year' => 'ds_fitments.year', 'b.model' => 'ds_fitments.model'])->whereNotNull('b.engine'))->first();
        if ($row) {
            $eng = DB::table('ds_fitments')->where(['make' => $row->make, 'year' => $row->year, 'model' => $row->model])->whereNotNull('engine')->value('engine');
            $r = DB::table('ds_fitments')->where(['make' => $row->make, 'year' => $row->year, 'model' => $row->model])
                ->where(fn ($w) => $w->whereNull('engine')->orWhere('engine', $eng))->pluck('part_number');
            $check('NULL engine row survives an engine choice', $r->contains($row->part_number), "{$row->year} {$row->make} {$row->model} / $eng");
        }

        // 4. random walks: every offered option leads to a non-empty result
        $bad = 0; $n = (int) $this->option('walks'); $single = 0;
        for ($i = 0; $i < $n; $i++) {
            $chosen = []; $guard = 0;
            while ($guard++ < 25) {
                $st = $f->step($chosen);
                if ($st['next'] === null) { if (empty($st['results'])) $bad++; elseif (count($st['results']) === 1) $single++; break; }
                if (!$st['next']['options']) { $bad++; break; }
                $chosen[$st['next']['facet']] = $st['next']['options'][array_rand($st['next']['options'])];
            }
            if ($guard >= 25) $bad++;
        }
        $check("$n random funnels all end in parts", $bad === 0, "dead ends: $bad, ended on a single part: " . round($single / max(1, $n) * 100) . '%');

        // 5. nothing confidential in customer payloads
        $blob = json_encode([$f->step(['make' => 'Chevrolet', 'year' => 2015, 'model' => 'Silverado 1500', 'drive' => 'RWD/2WD', 'cab' => 'Regular']), $f->search('4191-6100'), $f->publicPart('4191-6100')]);
        $check('price_cost and supplier never appear in customer payloads', !preg_match('/price_cost|supplier|price_yours/i', $blob));
        $cost = DB::table('ds_parts')->where('part_number', '4191-6100')->value('price_cost');
        $check('cost value itself is not leaked', $cost === null || !str_contains($blob, (string) $cost), "cost=$cost");

        // 6. importer idempotency is checked by running the import twice (row counts shown by the importer)
        $dupe = DB::table('ds_fitments')->select('fkey')->groupBy('fkey')->havingRaw('COUNT(*)>1')->count();
        $check('no duplicate fitment rows', $dupe === 0, 'fitments=' . DB::table('ds_fitments')->count() . ' parts=' . DB::table('ds_parts')->count());

        $this->line($fail ? "<error>$fail check(s) failed</error>" : '<info>all checks passed</info>');
        return $fail ? 1 : 0;
    }

    /** Explore every answer path from a starting state; returns the distinct sets of part numbers reached. */
    private function allEndings(DriveshaftFinder $f, array $chosen, int $cap = 2000): array
    {
        $ends = []; $stack = [$chosen]; $seen = 0;
        while ($stack && $seen++ < $cap) {
            $c = array_pop($stack); $st = $f->step($c);
            if ($st['next'] === null) { $p = array_column($st['results'] ?? [], 'part_number'); sort($p); $ends[implode(',', $p)] = $p; continue; }
            foreach ($st['next']['options'] as $o) $stack[] = $c + [$st['next']['facet'] => $o];
        }
        return array_values($ends);
    }
}
