<?php
/**
 * 部署检查 & 数据库初始化脚本
 * 运行方式: php check.php
 * 浏览器访问: http://你的域名/check.php
 */

echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>部署检查 - 留言管理系统</title>';
echo '<style>
    body { font-family: "PingFang SC","Microsoft YaHei",sans-serif; background:#f4f6fb; padding:40px; }
    .card { max-width:700px; margin:0 auto; background:#fff; border-radius:14px; box-shadow:0 4px 20px rgba(0,0,0,0.08); padding:32px; }
    h2 { color:#1a1a2e; margin-bottom:8px; }
    .sub { color:#8892a4; font-size:13px; margin-bottom:24px; }
    .row { display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid #f0f2f5; }
    .label { font-size:14px; color:#333; }
    .val { font-size:13px; }
    .ok { color:#1e8e3e; }
    .err { color:#d93025; }
    pre { background:#1a1a2e; color:#e0e0e0; padding:16px; border-radius:10px; font-size:12px; overflow-x:auto; }
</style></head><body><div class="card">';
echo '<h2>&#x1F6A7; 部署检查 - 留言管理系统</h2>';
echo '<p class="sub">检查系统运行环境和数据库状态</p>';

// ===== 1. PHP 版本 =====
echo '<div class="row"><span class="label">PHP 版本</span><span class="val ' . (version_compare(PHP_VERSION, '7.4', '>=') ? 'ok' : 'err') . '">' . PHP_VERSION . (version_compare(PHP_VERSION, '7.4', '>=') ? ' &#x2705;' : ' &#x274C; (需要 >= 7.4)') . '</span></div>';

// ===== 2. PDO 扩展 =====
$hasPdo = extension_loaded('pdo') && extension_loaded('pdo_sqlite');
echo '<div class="row"><span class="label">PDO SQLite 扩展</span><span class="val ' . ($hasPdo ? 'ok' : 'err') . '">' . ($hasPdo ? '已安装 &#x2705;' : '未安装 &#x274C;') . '</span></div>';

// ===== 3. JSON 扩展 =====
echo '<div class="row"><span class="label">JSON 扩展</span><span class="val ' . (extension_loaded('json') ? 'ok' : 'err') . '">' . (extension_loaded('json') ? '已安装 &#x2705;' : '未安装 &#x274C;') . '</span></div>';

// ===== 4. data 目录权限 =====
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
$dataWritable = is_writable($dataDir);
echo '<div class="row"><span class="label">data/ 目录可写</span><span class="val ' . ($dataWritable ? 'ok' : 'err') . '">' . ($dataWritable ? '可写 &#x2705;' : '不可写 &#x274C; (请设置权限: chmod 755 data/)') . '</span></div>';

// ===== 5. 数据库初始化 =====
try {
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/src/Database.php';

    $db = Database::getInstance();
    $db->initTables();
    echo '<div class="row"><span class="label">数据库初始化</span><span class="val ok">成功 &#x2705;</span></div>';

    // 检查表
    $tables = ['messages', 'api_keys', 'status_dict', 'users', 'user_sources', 'sessions', 'rate_limits'];
    foreach ($tables as $t) {
        try {
            $cnt = $db->getPdo()->query("SELECT COUNT(*) FROM $t")->fetchColumn();
            echo '<div class="row"><span class="label">&nbsp;&nbsp;表: ' . $t . '</span><span class="val ok">' . $cnt . ' 条记录</span></div>';
        } catch (Exception $e) {
            echo '<div class="row"><span class="label">&nbsp;&nbsp;表: ' . $t . '</span><span class="val err">错误: ' . $e->getMessage() . '</span></div>';
        }
    }

} catch (Exception $e) {
    echo '<div class="row"><span class="label">数据库初始化</span><span class="val err">失败 &#x274C; ' . $e->getMessage() . '</span></div>';
}

// ===== 6. 默认管理员 =====
try {
    $admin = $db->queryOne("SELECT id, username, role FROM users WHERE username = 'admin'");
    echo '<div class="row"><span class="label">默认管理员</span><span class="val ' . ($admin ? 'ok' : 'err') . '">' . ($admin ? $admin['username'] . ' (' . $admin['role'] . ') &#x2705;' : '未创建 &#x274C;') . '</span></div>';
} catch (Exception $e) {
    echo '<div class="row"><span class="label">管理员检查</span><span class="val err">失败</span></div>';
}

// ===== 7. API Key =====
try {
    $keys = $db->query("SELECT site_name, api_key, substr(api_key, 1, 6) || '****' || substr(api_key, -4) as masked_key FROM api_keys WHERE is_active = 1");
    echo '<div class="row"><span class="label">API Keys</span><span class="val ok">' . count($keys) . ' 个 &#x2705;</span></div>';
    foreach ($keys as $k) {
        echo '<div class="row"><span class="label">&nbsp;&nbsp;' . htmlspecialchars($k['site_name']) . '</span><span class="val" style="font-size:11px;">' . htmlspecialchars($k['masked_key']) . '</span></div>';
    }
} catch (Exception $e) {
    echo '<div class="row"><span class="label">API Keys</span><span class="val err">失败</span></div>';
}

// ===== 8. API 自检 =====
echo '<div class="row"><span class="label">API 自检</span>';
if (empty($keys)) {
    echo '<span class="val">未创建 API Key，跳过自检</span></div>';
} else {
    $ch = curl_init((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/api/business-inquiry.php');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['api_key' => $keys[0]['api_key'], 'name' => 'Check', 'phone' => '13800000000']),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        echo '<span class="val err">无法自检 (cURL错误: ' . htmlspecialchars($err) . ')</span></div>';
    } else {
        $resp = json_decode($result, true);
        $code = $resp['code'] ?? -1;
        $isOk = ($code === 0 || $code === 2003); // 2003 = duplicate, which means API is working
        echo '<span class="val ' . ($isOk ? 'ok' : 'err') . '">HTTP ' . $httpCode . ', code=' . $code . ($isOk ? ' &#x2705; API正常' : ' &#x274C;') . '</span></div>';
        if ($code !== 0 && $code !== 2003) {
            echo '<div class="row"><span class="label">API 响应</span><span class="val" style="font-size:11px;">' . htmlspecialchars($result) . '</span></div>';
        }
    }
}

// ===== 使用提示 =====
echo '<div style="margin-top:24px;padding:16px;background:#f8fafc;border-radius:10px;">';
echo '<strong style="font-size:14px;">&#x1F4A1; 使用提示：</strong><br><br>';
echo '1. 登录地址: <code>' . dirname($_SERVER['SCRIPT_NAME']) . '/admin/login.html</code><br>';
echo '2. 管理员账号: <code>admin</code>（请使用部署时设置的强密码，并在首次登录后修改）<br>';
echo '3. API 地址示例: <code>' . dirname($_SERVER['SCRIPT_NAME']) . '/api/business-inquiry.php</code><br>';
echo '</div>';

echo '</div></body></html>';
