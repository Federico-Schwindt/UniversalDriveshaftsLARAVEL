<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;

/**
 * One MySQL connection per request instead of two (owner, 2026-11-13). The catalog's `wp` connection and the app's
 * `mysql` connection point at the same database on Hostinger, and a request that used both opened two connections,
 * which doubled what counts against the account's limit ("SQLSTATE[HY000] [2002] Operation not permitted" when cron
 * jobs and visitors ran at once). When server, database, user, charset and prefix match, `wp` keeps its own
 * connection object (its name, grammar and events) but runs on mysql's PDO.
 *
 * The two differ in sql_mode: the app is strict, the WordPress tables are not (zero dates, loose GROUP BY). The shared
 * session switches mode only when the next query belongs to the other side (one SET; a catalog page alternates at
 * most a few times), so each side keeps exactly the rules it had. Transactions: the app never opens one on `wp`;
 * a mysql transaction now also covers wp writes made inside it (tests/TestCase.php trims 'wp' from
 * connectionsToTransact for the same reason). Off with DB_SHARE_WP=false. Covered by SharedWpConnectionTest.
 */
class SharedWpConnection
{
    public const WP_MODE = 'NO_ENGINE_SUBSTITUTION';   // what Laravel sets for 'strict' => false

    /** @var \WeakMap<\PDO, string>|null which side ('app' or 'wp') each shared session is in */
    private static ?\WeakMap $side = null;

    /** @var \WeakMap<Connection, bool>|null mysql connections that already switch back before their queries */
    private static ?\WeakMap $hooked = null;

    public static function install(DatabaseManager $db): void
    {
        $db->extend('wp', function (array $config, string $name) use ($db) {
            $conn = app('db.factory')->make($config, $name);
            if (!self::shares($config)) return $conn;
            $conn->setPdo(fn () => $db->connection('mysql')->getPdo());
            $conn->setReadPdo(fn () => $db->connection('mysql')->getReadPdo());
            $conn->beforeExecuting(fn ($sql, $bindings, Connection $c) => self::side($c->getPdo(), 'wp'));
            self::hookApp($db->connection('mysql'));
            return $conn;
        });
    }

    /** Whether the wp connection can run on mysql's session. */
    public static function shares(array $wp): bool
    {
        if (!config('database.share_wp', true)) return false;
        $m = config('database.connections.mysql');
        if (!is_array($m) || ($m['driver'] ?? '') !== 'mysql' || ($wp['driver'] ?? '') !== 'mysql') return false;
        if (!empty($m['url']) || !empty($m['unix_socket']) || !empty($wp['url'])) return false;
        foreach (['host', 'database', 'username', 'password', 'charset', 'collation', 'prefix'] as $k) {
            if ((string) ($m[$k] ?? '') !== (string) ($wp[$k] ?? '')) return false;
        }
        return (string) ($m['port'] ?? '3306') === (string) ($wp['port'] ?? '3306');
    }

    private static function hookApp(Connection $mysql): void
    {
        self::$hooked ??= new \WeakMap();
        if (isset(self::$hooked[$mysql])) return;
        self::$hooked[$mysql] = true;
        $mysql->beforeExecuting(fn ($sql, $bindings, Connection $c) => self::side($c->getPdo(), 'app'));
    }

    /** Put the shared session in the side's sql_mode, if the last query left it in the other one. */
    private static function side(?\PDO $pdo, string $want): void
    {
        if (!$pdo) return;
        self::$side ??= new \WeakMap();
        if ((self::$side[$pdo] ?? 'app') === $want) return;   // a fresh session is mysql's: strict
        // the app's own mode is read from the session itself, so it is exactly what Laravel set for 'strict' => true
        $pdo->exec($want === 'wp'
            ? "SET @uds_app_mode = @@SESSION.sql_mode, SESSION sql_mode = '" . self::WP_MODE . "'"
            : 'SET SESSION sql_mode = @uds_app_mode');
        self::$side[$pdo] = $want;
    }
}
