<?php
/**
 * 数据库连接类 - PDO SQLite 封装
 */

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $path = DB_PATH;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->pdo = new PDO(DB_DSN);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec('PRAGMA foreign_keys=ON');
        $this->pdo->exec('PRAGMA encoding="UTF-8"');
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo() {
        return $this->pdo;
    }

    /**
     * 初始化数据库表
     */
    public function initTables() {
        $sqlFile = __DIR__ . '/../sql/init.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $statements = array_filter(
                explode(';', $sql),
                function($s) { return trim($s) !== ''; }
            );
            foreach ($statements as $stmt) {
                try {
                    $this->pdo->exec($stmt);
                } catch (PDOException $e) {
                    // 表已存在则忽略
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        throw $e;
                    }
                }
            }
        }

        // 对既有数据库执行轻量迁移；CREATE TABLE IF NOT EXISTS 不会补充新列。
        $columns = array_column($this->query('PRAGMA table_info(messages)'), 'name');
        $migrations = [
            'type'       => "ALTER TABLE messages ADD COLUMN type TEXT NOT NULL DEFAULT ''",
            'company'    => 'ALTER TABLE messages ADD COLUMN company TEXT DEFAULT NULL',
            'country'    => 'ALTER TABLE messages ADD COLUMN country TEXT DEFAULT NULL',
            'source_url' => 'ALTER TABLE messages ADD COLUMN source_url TEXT DEFAULT NULL',
            'extra_data' => 'ALTER TABLE messages ADD COLUMN extra_data TEXT DEFAULT NULL'
        ];
        foreach ($migrations as $column => $statement) {
            if (!in_array($column, $columns, true)) {
                $this->pdo->exec($statement);
            }
        }
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_type ON messages(type)');

        $userColumns = array_column($this->query('PRAGMA table_info(users)'), 'name');
        if (!in_array('can_view_china', $userColumns, true)) {
            $this->pdo->exec('ALTER TABLE users ADD COLUMN can_view_china INTEGER DEFAULT 0');
        }
        $keyColumns = array_column($this->query('PRAGMA table_info(api_keys)'), 'name');
        if (!in_array('site_scope', $keyColumns, true)) {
            $this->pdo->exec("ALTER TABLE api_keys ADD COLUMN site_scope TEXT DEFAULT 'overseas'");
        }
        $this->pdo->exec("UPDATE api_keys SET site_scope='china' WHERE site_name='三奇国内官网'");

        // 将早期版本误存入海外表的国内官网留言迁移到独立表。
        $legacyChinaRows = $this->query("SELECT * FROM messages WHERE type='china-website-inquiry'");
        foreach ($legacyChinaRows as $row) {
            $extra = json_decode($row['extra_data'] ?? '', true) ?: [];
            $this->execute(
                'INSERT OR IGNORE INTO china_website_messages
                 (id,inquiry_type,region,name,company,phone,email,remark,preferred_contact,source_url,source,api_key,ip_address,user_agent,status,handle_record,handler,created_at,handled_at,handled_by)
                 VALUES (:id,:inquiry_type,:region,:name,:company,:phone,:email,:remark,:preferred_contact,:source_url,:source,:api_key,:ip,:ua,:status,:record,:handler,:created,:handled,:handled_by)',
                [
                    ':id'=>$row['id'], ':inquiry_type'=>$extra['inquiry_type']??null, ':region'=>$extra['region']??null,
                    ':name'=>$row['name']??null, ':company'=>$row['company']??null, ':phone'=>$row['phone']??null,
                    ':email'=>$row['email']??null, ':remark'=>$row['remark']??null, ':preferred_contact'=>$extra['preferred_contact']??null,
                    ':source_url'=>$row['source_url']??null, ':source'=>$row['source'], ':api_key'=>$row['api_key'],
                    ':ip'=>$row['ip_address']??null, ':ua'=>$row['user_agent']??null, ':status'=>$row['status']??0,
                    ':record'=>$row['handle_record']??null, ':handler'=>$row['handler']??null, ':created'=>$row['created_at']??null,
                    ':handled'=>$row['handled_at']??null, ':handled_by'=>$row['handled_by']??null
                ]
            );
            $this->execute("DELETE FROM messages WHERE id=:id AND type='china-website-inquiry'", [':id'=>$row['id']]);
        }
    }

    /**
     * 执行查询并返回所有结果
     */
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 执行查询并返回单行
     */
    public function queryOne($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * 执行插入/更新/删除
     */
    public function execute($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * 获取最后插入ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
}
