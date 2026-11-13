<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * When `wp` runs on mysql's session (App\Support\SharedWpConnection, DB_SHARE_WP=true) the mysql transaction
     * already wraps its writes, and a second BEGIN on the same session would fail. (The DatabaseTransactions trait's
     * own connectionsToTransact() wins over one defined here, so the list is trimmed before the traits start.)
     */
    protected function setUpTraits()
    {
        if (property_exists($this, 'connectionsToTransact') && in_array('mysql', $this->connectionsToTransact, true)
            && \App\Support\SharedWpConnection::shares((array) config('database.connections.wp'))) {
            $this->connectionsToTransact = array_values(array_diff($this->connectionsToTransact, ['wp']));
        }
        return parent::setUpTraits();
    }
}
