<?php
class Database {
    private static $instance = null;
    private $pdo;
    private function __construct() {
        $this->pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]);
        $this->pdo->exec("SET time_zone = '+00:00'");
    }
    public static function getInstance() { if (self::$instance === null) self::$instance = new self(); return self::$instance; }
    public function getPdo() { return $this->pdo; }
    public function initTables() {
        static $initialized = false;
        if ($initialized) return;
        $sql = file_get_contents(__DIR__ . '/../sql/init.sql');
        if ($sql === false) throw new RuntimeException('无法读取数据库初始化脚本');
        $this->pdo->exec($sql);
        $column=$this->queryOne("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=:db AND TABLE_NAME='analytics_sessions' AND COLUMN_NAME='ip_address'",[':db'=>DB_NAME]);
        if((int)($column['c']??0)===0) {
            $this->pdo->exec('ALTER TABLE analytics_sessions ADD COLUMN ip_address VARCHAR(45) NULL AFTER visitor_hash, ADD INDEX idx_session_ip(site_id,ip_address,last_seen_at)');
        }
        $this->ensureColumn('analytics_sessions','landing_path','VARCHAR(1024) NULL AFTER landing_url');
        $this->ensureColumn('analytics_sessions','referrer_domain','VARCHAR(255) NULL AFTER referrer');
        $this->ensureColumn('analytics_sessions','country_code','CHAR(2) NULL AFTER country');
        $this->ensureColumn('analytics_sessions','locale','VARCHAR(32) NULL AFTER device_type');
        $this->ensureColumn('analytics_sessions','utm_source','VARCHAR(255) NULL AFTER locale');
        $this->ensureColumn('analytics_sessions','utm_medium','VARCHAR(255) NULL AFTER utm_source');
        $this->ensureColumn('analytics_sessions','utm_campaign','VARCHAR(255) NULL AFTER utm_medium');
        $this->ensureColumn('messages','inquiry_source','VARCHAR(80) NULL AFTER source');
        $this->ensureColumn('messages','product_key','VARCHAR(160) NULL AFTER inquiry_source');
        $this->ensureColumn('messages','utm_source','VARCHAR(255) NULL AFTER product_key');
        $this->ensureColumn('messages','utm_medium','VARCHAR(255) NULL AFTER utm_source');
        $this->ensureColumn('messages','utm_campaign','VARCHAR(255) NULL AFTER utm_medium');
        $this->ensureColumn('messages','first_touch_source','VARCHAR(80) NULL AFTER utm_campaign');
        $this->ensureColumn('messages','conversion_source','VARCHAR(80) NULL AFTER first_touch_source');
        $this->ensureColumn('api_clients','audience',"VARCHAR(160) NOT NULL DEFAULT 'sanqi-central-readonly' AFTER site_id");
        $initialized = true;
    }
    private function ensureColumn($table,$column,$definition) {
        $row=$this->queryOne('SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=:db AND TABLE_NAME=:table AND COLUMN_NAME=:column',[':db'=>DB_NAME,':table'=>$table,':column'=>$column]);
        if((int)($row['c']??0)===0) $this->pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
    public function query($sql, $params = []) { $s=$this->pdo->prepare($sql); $s->execute($params); return $s->fetchAll(); }
    public function queryOne($sql, $params = []) { $s=$this->pdo->prepare($sql); $s->execute($params); return $s->fetch(); }
    public function execute($sql, $params = []) { $s=$this->pdo->prepare($sql); $s->execute($params); return $s->rowCount(); }
    public function lastInsertId() { return $this->pdo->lastInsertId(); }
    public function beginTransaction() { return $this->pdo->beginTransaction(); }
    public function commit() { return $this->pdo->commit(); }
    public function rollBack() { return $this->pdo->rollBack(); }
}
