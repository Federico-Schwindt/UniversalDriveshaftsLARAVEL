<?php

namespace Tests\Feature;

use App\Support\SharedWpConnection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Owner, 2026-11-13: one database connection a request instead of two (Hostinger's connection limit). The `wp`
 * connection runs on mysql's session and each side keeps its own sql_mode.
 */
class SharedWpConnectionTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['mysql'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.share_wp' => true]);
        DB::purge('wp');
    }

    protected function tearDown(): void
    {
        DB::purge('wp');
        parent::tearDown();
    }

    public function test_wp_runs_on_the_mysql_session(): void
    {
        $this->assertSame(DB::connection('mysql')->getPdo(), DB::connection('wp')->getPdo());
        $this->assertSame(DB::connection('mysql')->selectOne('select connection_id() id')->id, DB::connection('wp')->selectOne('select connection_id() id')->id);
        $this->assertSame('wp', DB::connection('wp')->getName(), 'the catalog keeps its own connection object');
    }

    public function test_each_side_keeps_its_sql_mode(): void
    {
        $mode = fn (string $c) => DB::connection($c)->selectOne('select @@SESSION.sql_mode m')->m;
        $strict = $mode('mysql');
        $this->assertStringContainsString('STRICT_TRANS_TABLES', $strict);
        $this->assertSame(SharedWpConnection::WP_MODE, $mode('wp'));
        $this->assertSame($strict, $mode('mysql'), 'back to exactly the strict mode Laravel set');
        $this->assertSame(SharedWpConnection::WP_MODE, $mode('wp'));

        // WordPress rows carry zero dates: fine through wp, refused by the strict app side
        DB::connection('mysql')->statement('create temporary table uds_share_t (id int primary key, d datetime not null)');
        DB::connection('wp')->insert("insert into uds_share_t values (1, '0000-00-00 00:00:00')");
        $this->assertSame(1, DB::connection('wp')->table('uds_share_t')->count());
        try {
            DB::connection('mysql')->insert("insert into uds_share_t values (2, '0000-00-00 00:00:00')");
            $this->fail('the strict side accepted a zero date');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Incorrect datetime', $e->getMessage());
        }
        DB::connection('mysql')->statement('drop temporary table uds_share_t');
    }

    public function test_it_only_shares_when_both_name_the_same_database(): void
    {
        $wp = config('database.connections.wp');
        $this->assertTrue(SharedWpConnection::shares($wp));
        $this->assertFalse(SharedWpConnection::shares(['database' => 'another_db'] + $wp));
        $this->assertFalse(SharedWpConnection::shares(['username' => 'someone_else'] + $wp));
        config(['database.share_wp' => false]);
        $this->assertFalse(SharedWpConnection::shares($wp));
    }

    public function test_pages_render_with_one_connection(): void
    {
        $this->get('/')->assertOk();
        $this->get('/shop')->assertOk();
        $this->assertSame(DB::connection('mysql')->getPdo(), DB::connection('wp')->getPdo());
    }
}
