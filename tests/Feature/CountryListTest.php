<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Owner, 2026-11-13: the checkout's country list looked messy. The United States first, then A to Z, "Other" last. */
class CountryListTest extends TestCase
{
    public function test_countries_are_alphabetical_after_the_united_states(): void
    {
        $c = config('countries');
        $this->assertSame('US', array_key_first($c));
        $this->assertSame('OTHER', array_key_last($c));
        $middle = array_values(array_slice($c, 1, -1));
        $sorted = $middle;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $middle);
        $this->assertCount(97, $c);
    }
}
