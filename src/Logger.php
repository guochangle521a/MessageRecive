<?php
/**
 * 简易日志类
 * 记录留言提交等重要操作日志
 */
class Logger {

    const LOG_DIR = __DIR__ . '/../data/logs/';

    /**
     * 写入日志
     * @param string $action  操作类型 (submit/update/delete/login)
     * @param array  $data    附加数据
     * @param string $level   INFO/WARNING/ERROR
     */
    public static function log($action, $data = [], $level = 'INFO') {
        try {
            self::ensureDir();

            $logFile = self::LOG_DIR . 'app_' . date('Y-m-d') . '.log';

            $entry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'level'     => $level,
                'action'    => $action,
                'ip'        => self::getIP(),
                'data'      => $data
            ];

            $line = json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // 日志写入失败不应中断业务
        }
    }

    /**
     * 错误日志
     */
    public static function error($action, $data = []) {
        self::log($action, $data, 'ERROR');
    }

    /**
     * 获取客户端IP
     */
    private static function getIP() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * 确保日志目录存在
     */
    private static function ensureDir() {
        if (!is_dir(self::LOG_DIR)) {
            mkdir(self::LOG_DIR, 0755, true);
            // 写入 .htaccess 防止直接访问
            if (!file_exists(self::LOG_DIR . '.htaccess')) {
                file_put_contents(self::LOG_DIR . '.htaccess', "Deny from all\n");
            }
        }
    }
}
