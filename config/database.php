<?php
/** MySQL 与平台基础配置。生产环境请通过环境变量注入，不要提交真实密钥。 */
$local = file_exists(__DIR__.'/local.php') ? require __DIR__.'/local.php' : [];
define('DB_HOST', getenv('SANQI_DB_HOST') ?: ($local['host'] ?? '127.0.0.1'));
define('DB_PORT', getenv('SANQI_DB_PORT') ?: ($local['port'] ?? '3306'));
define('DB_NAME', getenv('SANQI_DB_NAME') ?: ($local['name'] ?? 'sanqi_data_platform'));
define('DB_USER', getenv('SANQI_DB_USER') ?: ($local['user'] ?? 'sanqi_app'));
define('DB_PASS', getenv('SANQI_DB_PASS') ?: ($local['pass'] ?? ''));
define('DB_DSN', 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4');
define('VISITOR_HASH_SECRET', getenv('SANQI_VISITOR_SECRET') ?: ($local['visitor_secret'] ?? 'change-this-in-production'));
date_default_timezone_set('Asia/Shanghai');
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
define('RATE_LIMIT_MAX', 10);
define('RATE_LIMIT_WINDOW', 60);
define('DUPLICATE_WINDOW', 60);
define('TRACK_RATE_LIMIT_MAX', 120);
define('TRACK_SESSION_TIMEOUT', 180);
define('RAW_EVENT_RETENTION_DAYS', 90);
define('CORS_ORIGINS', getenv('SANQI_CORS_ORIGINS') ?: '*');
