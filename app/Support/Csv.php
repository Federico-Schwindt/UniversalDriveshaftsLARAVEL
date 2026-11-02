<?php

namespace App\Support;

class Csv
{
    /** fputcsv that neutralises spreadsheet formulas (=, +, -, @, tab, CR) in text cells. */
    public static function put($handle, array $row): void
    {
        $row = array_map(fn ($v) => is_string($v) && $v !== '' && !is_numeric($v) && strpbrk($v[0], "=+-@\t\r") !== false ? "'" . $v : $v, $row);
        fputcsv($handle, $row);
    }
}
