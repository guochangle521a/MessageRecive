<?php
/**
 * 数据库配置文件
 * SQLite 单文件数据库
 */

define('DB_PATH', __DIR__ . '/../data/messages.db');
define('DB_DSN', 'sqlite:' . DB_PATH);

// 时区
date_default_timezone_set('Asia/Shanghai');

// Session 安全配置
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

// 频率限制
define('RATE_LIMIT_MAX', 10);      // 每分钟最大请求数
define('RATE_LIMIT_WINDOW', 60);   // 窗口时间（秒）
define('DUPLICATE_WINDOW', 60);    // 同IP同手机号防重复窗口（秒）

// CORS 允许的域名（多个用逗号分隔）
define('CORS_ORIGINS', '*');
