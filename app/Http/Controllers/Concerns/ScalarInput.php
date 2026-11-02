<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/** Query-string reads that never 500: ?q[]=x arrives as an array, and (string) on it throws "Array to string conversion". */
trait ScalarInput
{
    protected function qs(Request $r, string $key, string $default = ''): string
    {
        $v = $r->query($key);
        return is_scalar($v) ? (string) $v : $default;
    }
}
