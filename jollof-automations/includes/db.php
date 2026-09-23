<?php
/**
 * Jollof Automations — Database Access Layer (PDO)
 */
declare(strict_types=1);

final class AutoDB
{
    private static ?PDO $pdo = null;
    private static bool $connectionFailed = false;

    public static function pdo(): ?PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        if (self::$connectionFailed) {
            return null;
        }

        $cfg = ja_config('db');
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 3,
        ];

        try {
            if (($cfg['driver'] ?? 'mysql') === 'sqlite') {
                $path = $cfg['sqlite_path'] ?? (JA_ROOT . '/storage/automations.sqlite');
                if (!is_dir(dirname($path))) {
                    @mkdir(dirname($path), 0775, true);
                }
                self::$pdo = new PDO('sqlite:' . $path, null, null, $opts);
                self::$pdo->exec('PRAGMA foreign_keys = ON');
                return self::$pdo;
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'] ?? 'localhost',
                (int) ($cfg['port'] ?? 3306),
                $cfg['name'] ?? 'wal7zkit_jollof',
                $cfg['charset'] ?? 'utf8mb4'
            );

            self::$pdo = new PDO($dsn, $cfg['user'] ?? 'root', $cfg['pass'] ?? '', $opts);
            return self::$pdo;
        } catch (\Throwable $e) {
            self::$connectionFailed = true;
            if (ja_config('debug', false)) {
                error_log('AutoDB Connection Notice: ' . $e->getMessage());
            }
            return null;
        }
    }

    public static function isConnected(): bool
    {
        return self::pdo() instanceof PDO;
    }

    public static function all(string $sql, array $params = []): array
    {
        $pdo = self::pdo();
        if (!$pdo) return [];
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('AutoDB Error: ' . $e->getMessage());
            return [];
        }
    }

    public static function row(string $sql, array $params = []): ?array
    {
        $pdo = self::pdo();
        if (!$pdo) return null;
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $res = $stmt->fetch();
            return is_array($res) ? $res : null;
        } catch (\Throwable $e) {
            error_log('AutoDB Error: ' . $e->getMessage());
            return null;
        }
    }

    public static function insert(string $table, array $data): int
    {
        $pdo = self::pdo();
        if (!$pdo || empty($data)) return 0;
        try {
            $cols = array_keys($data);
            $quotedCols = array_map(fn($c) => '`' . str_replace('`', '', $c) . '`', $cols);
            $placeholders = array_map(fn($c) => ':' . $c, $cols);
            $sql = 'INSERT INTO `' . str_replace('`', '', $table) . '` (' . implode(', ', $quotedCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            return (int) $pdo->lastInsertId();
        } catch (\Throwable $e) {
            error_log('AutoDB Insert Error: ' . $e->getMessage());
            return 0;
        }
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $pdo = self::pdo();
        if (!$pdo || empty($data)) return 0;
        try {
            $sets = [];
            $params = [];
            foreach ($data as $col => $val) {
                $pName = 'set_' . str_replace('`', '', $col);
                $sets[] = '`' . str_replace('`', '', $col) . '` = :' . $pName;
                $params[$pName] = $val;
            }
            $sql = 'UPDATE `' . str_replace('`', '', $table) . '` SET ' . implode(', ', $sets) . ' WHERE ' . $where;
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge($params, $whereParams));
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            error_log('AutoDB Update Error: ' . $e->getMessage());
            return 0;
        }
    }

    public static function tableExists(string $table): bool
    {
        $pdo = self::pdo();
        if (!$pdo) return false;
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE :tbl");
            $stmt->execute([':tbl' => $table]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
