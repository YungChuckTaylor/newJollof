<?php
/**
 * Jollof Living — database layer (PDO)
 * Small query helper used by every page and API endpoint.
 */
declare(strict_types=1);

// Defence in depth: these files are libraries, never entry points.
// .htaccess blocks the folder as well, but a mis-configured host must not leak them.
if (!defined('JL_ROOT')) {
    http_response_code(404);
    exit;
}


final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg = config('db');
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if (($cfg['driver'] ?? 'mysql') === 'sqlite') {
            $path = $cfg['sqlite_path'];
            if (!is_dir(dirname($path))) {
                @mkdir(dirname($path), 0775, true);
            }
            self::$pdo = new PDO('sqlite:' . $path, null, null, $opts);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            (int) ($cfg['port'] ?? 3306),
            $cfg['name'],
            $cfg['charset'] ?? 'utf8mb4'
        );

        try {
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $opts);
        } catch (PDOException $e) {
            if (config('debug')) {
                throw $e;
            }
            http_response_code(503);
            exit('The site is temporarily unavailable. (Database connection failed.)');
        }

        return self::$pdo;
    }

    public static function isSqlite(): bool
    {
        return (config('db')['driver'] ?? 'mysql') === 'sqlite';
    }

    /** Run a statement and return the PDOStatement. */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** All rows. */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** First row or null. */
    public static function row(string $sql, array $params = []): ?array
    {
        $r = self::run($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    /** Single scalar value. */
    public static function value(string $sql, array $params = [], $default = null)
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? $default : $v;
    }

    /** Key/value map from a two-column query. */
    public static function pairs(string $sql, array $params = []): array
    {
        $out = [];
        foreach (self::all($sql, $params) as $r) {
            $vals = array_values($r);
            $out[$vals[0]] = $vals[1] ?? null;
        }
        return $out;
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', array_map(static fn($c) => ':' . $c, $cols))
        );
        self::run($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * UPDATE with either style of WHERE placeholder.
     *
     *   DB::update('users', ['name' => 'A'], 'id = ?', [7])
     *   DB::update('users', ['name' => 'A'], 'id = :id', ['id' => 7])
     *
     * PDO cannot mix named and positional markers in one statement, so when
     * the WHERE clause uses '?' the SET clause is built positionally too.
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        if (strpos($where, '?') !== false) {
            $set = implode(', ', array_map(static fn($c) => "$c = ?", array_keys($data)));
            $params = array_merge(array_values($data), array_values($whereParams));
            return self::run("UPDATE $table SET $set WHERE $where", $params)->rowCount();
        }

        $set = implode(', ', array_map(static fn($c) => "$c = :s_$c", array_keys($data)));
        $params = $whereParams;
        foreach ($data as $k => $v) {
            $params['s_' . $k] = $v;
        }
        return self::run("UPDATE $table SET $set WHERE $where", $params)->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::run("DELETE FROM $table WHERE $where", $params)->rowCount();
    }

    public static function tableExists(string $table): bool
    {
        try {
            self::run("SELECT 1 FROM $table LIMIT 1");
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function begin(): void  { self::pdo()->beginTransaction(); }
    public static function commit(): void { self::pdo()->commit(); }
    public static function rollback(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }
}
