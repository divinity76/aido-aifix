<?php

declare(strict_types=1);

class Cache
{
    private \PDO $db;
    private function __construct()
    {
        $dbFile = __DIR__ . DIRECTORY_SEPARATOR . "cache.db3";
        $exists = file_exists($dbFile);
        $this->db = new \PDO("sqlite:$dbFile", null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);
        //$this->db->exec("PRAGMA journal_mode=WAL");
        $this->db->exec("PRAGMA journal_mode=MEMORY");
        $this->db->exec("PRAGMA synchronous=OFF");
        if (!$exists) {
            $this->db->exec("CREATE TABLE cache (key BLOB PRIMARY KEY, creation_timestamp INTEGER, data BLOB)");
        }
    }
    private static function Instance(): Cache
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new Cache();
        }
        return $instance;
    }
    public static function get(string|array $key, int $max_age = 24 * 60 * 60): mixed
    {
        if (is_array($key)) {
            $key = serialize($key);
        }
        $instance = self::Instance();
        if ($max_age > 0) {
            $stmt = $instance->db->prepare("SELECT data FROM cache WHERE key = ? AND creation_timestamp > ?");
            $stmt->execute([$key, time() - $max_age]);
            $row = $stmt->fetch();
            $stmt->closeCursor();
            if ($row) {
                return unserialize($row['data']);
            }
        }
        return null;
    }
    public static function set(string|array $key, $data): void
    {
        if (is_array($key)) {
            $key = serialize($key);
        }
        $instance = self::Instance();
        $row = [
            "key" => $key,
            "creation_timestamp" => time(),
            "data" => serialize($data),
        ];
        $stmt = $instance->db->prepare("INSERT OR REPLACE INTO cache (key, creation_timestamp, data) VALUES (:key, :creation_timestamp, :data)");
        $stmt->execute($row);
    }
}
