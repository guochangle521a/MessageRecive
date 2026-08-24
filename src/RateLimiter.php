<?php
/**
 * 频率限制类
 * 基于 SQLite 实现简单的 IP 频率限制
 * 生产环境建议使用 Redis
 */

class RateLimiter {

    /**
     * 检查 IP 是否超出频率限制
     * @return bool true = 允许, false = 被限制
     */
    public static function check($ip, $maxRequests = null, $window = null, $actionType = 'submit') {
        if ($maxRequests === null) $maxRequests = RATE_LIMIT_MAX;
        if ($window === null) $window = RATE_LIMIT_WINDOW;

        $db = Database::getInstance();

        // 清理过期记录
        $expireTime = date('Y-m-d H:i:s', time() - $window);
        $db->execute(
            'DELETE FROM rate_limits WHERE created_at < :et',
            [':et' => $expireTime]
        );

        // 检查当前窗口内请求数
        $row = $db->queryOne(
            'SELECT COUNT(*) as cnt FROM rate_limits
             WHERE ip_address = :ip AND action_type = :at AND created_at >= :et',
            [':ip' => $ip, ':at' => $actionType, ':et' => $expireTime]
        );

        if ($row['cnt'] >= $maxRequests) {
            return false;
        }

        // 记录本次请求
        $db->execute(
            'INSERT INTO rate_limits (ip_address, action_type) VALUES (:ip, :at)',
            [':ip' => $ip, ':at' => $actionType]
        );
        return true;
    }

    /**
     * 检查重复提交（同IP + 同手机号 60秒内）
     */
    public static function checkDuplicate($ip, $phone, $table = 'messages') {
        $db = Database::getInstance();
        if (!in_array($table, ['messages', 'china_website_messages'], true)) $table = 'messages';
        $window = DUPLICATE_WINDOW;
        $expireTime = date('Y-m-d H:i:s', time() - $window);

        $row = $db->queryOne(
            'SELECT COUNT(*) as cnt FROM ' . $table . '
             WHERE ip_address = :ip AND phone = :ph AND created_at >= :et',
            [':ip' => $ip, ':ph' => $phone, ':et' => $expireTime]
        );

        return $row['cnt'] == 0;
    }

    /**
     * 获取客户端真实IP
     */
    public static function getClientIP() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
