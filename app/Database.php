<?php
declare(strict_types=1);
final class DB {
    private static ?PDO $pdo=null;
    public static function connection(): PDO {
        global $config;
        if (!self::$pdo) {
            self::$pdo = new PDO("mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",$config['db_user'],$config['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
            self::$pdo->exec("SET time_zone = '+08:00'");
        }
        return self::$pdo;
    }
    public static function run(string $sql,array $args=[]): PDOStatement { $s=self::connection()->prepare($sql); $s->execute($args); return $s; }
    public static function one(string $sql,array $args=[]): ?array { return self::run($sql,$args)->fetch() ?: null; }
    public static function all(string $sql,array $args=[]): array { return self::run($sql,$args)->fetchAll(); }
    public static function id(): int { return (int)self::connection()->lastInsertId(); }
    public static function transaction(callable $fn): mixed {
        $p=self::connection(); $p->beginTransaction();
        try { $r=$fn(); $p->commit(); return $r; } catch(Throwable $e) { if($p->inTransaction())$p->rollBack(); throw $e; }
    }
}
