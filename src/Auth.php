<?php
/**
 * 认证模块 - 支持 Token + Session 双模式
 */

class Auth {

    // Token 过期时间（小时）
    const TOKEN_EXPIRE_HOURS = 24;

    /**
     * 验证 API Key 并返回站点信息
     */
    public static function validateApiKey($apiKey)
    {
        $db = Database::getInstance();
        return $db->queryOne(
            'SELECT * FROM api_keys WHERE api_key = :k AND is_active = 1',
            [':k' => trim($apiKey)]
        );
    }

    /**
     * 用户登录（返回 Token）
     */
    public static function login($username, $password)
    {
        $db = Database::getInstance();
        $user = $db->queryOne('SELECT * FROM users WHERE username = :u AND is_active = 1', [
            ':u' => $username
        ]);

        if (!$user || !password_verify($password, $user['password'])) {
            return [
                'success' => false,
                'msg' => '用户名或密码错误'
            ];
        }

        // 生成 Token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_EXPIRE_HOURS * 3600);

        // 清理过期会话
        $db->execute("DELETE FROM sessions WHERE expires_at < datetime('now','localtime')");

        // 删除该用户旧会话（单点登录）
        $db->execute('DELETE FROM sessions WHERE user_id = :uid', [
            ':uid' => $user['id']
        ]);

        // 创建新会话
        $db->execute(
            'INSERT INTO sessions (user_id, token, ip_address, user_agent, expires_at) VALUES (:uid, :tok, :ip, :ua, :exp)',
            [
                ':uid' => $user['id'],
                ':tok' => $token,
                ':ip'  => self::getClientIP(),
                ':ua'  => $_SERVER['HTTP_USER_AGENT'] ?? '',
                ':exp' => $expiresAt
            ]
        );

        return [
            'success' => true,
            'msg' => '登录成功',
            'data' => [
                'token'     => $token,
                'id'        => $user['id'],
                'username'  => $user['username'],
                'role'      => $user['role'],
                'real_name' => $user['real_name']
            ]
        ];
    }

    /**
     * 退出登录
     */
    public static function logout($token = null)
    {
        $db = Database::getInstance();
        if ($token) {
            $db->execute('DELETE FROM sessions WHERE token = :t', [
                ':t' => $token
            ]);
        }
        // 兼容 Session 模式
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * 验证登录状态，返回用户信息
     * 支持 Token (Authorization: Bearer xxx) 和 Session 两种方式
     */
    public static function getAuthUser()
    {
        // 方式1: Token 认证 (Authorization Header)
        $headers = self::getAllHeaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            $token = trim($m[1]);
            $db = Database::getInstance();
            $session = $db->queryOne(
                "SELECT s.*, u.id as uid, u.username, u.role, u.real_name, u.is_active
                 FROM sessions s
                 JOIN users u ON u.id = s.user_id
                 WHERE s.token = :t AND s.expires_at > datetime('now','localtime')",
                [':t' => $token]
            );
            if ($session && $session['is_active']) {
                return [
                    'id'        => $session['uid'],
                    'username'  => $session['username'],
                    'role'      => $session['role'],
                    'real_name' => $session['real_name']
                ];
            }
        }

        // 方式2: Session 认证（浏览器Cookie）
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (!empty($_SESSION['user'])) {
            return $_SESSION['user'];
        }

        return null;
    }

    /**
     * 强制要求登录（未登录返回 401）
     */
    public static function requireLogin()
    {
        $user = self::getAuthUser();
        if (!$user) {
            Response::error(401, '请先登录', 401);
        }
        return $user;
    }

    /**
     * 强制要求管理员权限
     */
    public static function requireAdmin()
    {
        $user = self::requireLogin();
        if ($user['role'] !== 'admin') {
            Response::error(403, '无权限，仅管理员可操作', 403);
        }
        return $user;
    }

    /**
     * 获取用户的可见来源列表（非管理员受限于 user_sources）
     */
    public static function getUserSources($user)
    {
        if ($user['role'] === 'admin') {
            return null; // null = 全部可见
        }
        $rows = Database::getInstance()->query(
            'SELECT source FROM user_sources WHERE user_id = :uid',
            [':uid' => $user['id']]
        );
        return array_column($rows, 'source');
    }

    /**
     * 获取客户端真实IP
     */
    private static function getClientIP()
    {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '127.0.0.1';
    }

    /**
     * 跨平台获取 HTTP 请求头
     */
    private static function getAllHeaders()
    {
        if (function_exists('getallheaders')) {
            return array_change_key_case(getallheaders(), CASE_LOWER);
        }
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerKey = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($headerKey)] = $value;
            }
        }
        return $headers;
    }
}
