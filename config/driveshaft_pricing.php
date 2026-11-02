<?php
/**
 * Selling-price rules for the complete-driveshaft catalog.
 * Fede, 2026-10-24: a flat dollar amount added to PTI's price per make (or per model where listed);
 * any make or model not listed sells at PTI's price. Replaces the 2026-09-19 rules (German +50%,
 * Grand Cherokee +$250; backup in storage/app/driveshaft_pricing.backup-20261024.php).
 * The price is per PART, not per vehicle: if a part fits any vehicle that matches a rule, the rule applies
 * to that part everywhere. When several rules match one part, the HIGHEST resulting price wins.
 * `models` = exact model names as stored in ds_fitments. After editing run:  php artisan catalog:price-driveshafts
 * A manual ds_parts.price_retail always beats these rules.
 */
$add = fn (string $make, int $amount, array $models = []) => array_filter(['name' => mb_strimwidth($make . ($models ? ' ' . implode('/', $models) : ''), 0, 50, '…') . ' +$' . $amount, 'makes' => [$make], 'models' => $models ?: null, 'add' => $amount]);

return [
    'base' => 'price_cost', // PTI's discounted price (what PTI shows us after our discount), refreshed by pti:driveshaft-prices (owner, 2026-10-24)
    'rules' => [
        $add('Acura', 250),
        $add('Audi', 280),
        $add('BMW', 300),
        $add('Buick', 290),
        $add('Cadillac', 270),
        $add('Chevrolet', 250, ['Astro', 'Equinox', 'Uplander', 'Venture', 'Trailblazer']),
        $add('Chrysler', 280),
        $add('Ford', 270, ['Aerostar']),
        $add('Ford', 150, ['Bronco', 'Bronco II']),
        $add('Ford', 250, ['Transit 150', 'Transit 250', 'Transit 350', 'Edge', 'Expedition', 'Capri']),
        $add('Ford', 300, ['Taurus', 'Ecosport', 'Focus', 'MKZ', 'Thunderbird']),
        $add('Ford', 290, ['Explorer']),
        $add('Ford', 280, ['Mustang']),
        $add('Dodge', 290, ['Avenger', 'Caliber', 'Caravan', 'Challenger', 'Charger', 'Durango', 'Dakota', 'Journey', 'Magnum', 'Nitro', 'Stealth', 'Viper']),
        $add('Eagle', 250),
        $add('Fiat', 290),
        $add('GMC', 300, ['Acadia', 'Canyon', 'Envoy', 'Envoy Denali', 'Jimmy', 'S15 Jimmy', 'Syclone', 'Terrain', 'Typhoon']),
        $add('Genesis', 300),
        $add('Geo', 300),
        $add('Honda', 250),
        $add('Hummer', 250),
        $add('Hyundai', 250),
        $add('Infiniti', 250),
        $add('International', 200),
        $add('Isuzu', 100),
        $add('Jaguar', 280),
        $add('Jeep', 200),
        $add('Kia', 200),
        $add('Land Rover', 200),
        $add('Lexus', 250),
        $add('Lincoln', 250),
        $add('Mazda', 250),
        $add('Mercedes', 290),
        $add('Mercury', 260),
        $add('Merkur', 250),
        $add('Mitsubishi', 250),
        $add('Nissan', 200),
        $add('Oldsmobile', 200),
        $add('Plymouth', 200),
        $add('Pontiac', 200),
        $add('Porsche', 250),
        $add('Range Rover', 270),
        $add('Saab', 250),
        $add('Saturn', 200),
        $add('Scion', 200),
        $add('Subaru', 200),
        $add('Suzuki', 200),
        $add('Toyota', 200),
        $add('Volkswagen', 280),
        $add('Volvo', 275),
    ],
    'default' => ['percent' => 0, 'add' => 0], // every other part: PTI's price
];
