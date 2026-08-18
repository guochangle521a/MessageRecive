-- ==========================================
-- 留言管理系统 - 数据库初始化脚本
-- 数据库文件: data/messages.db (SQLite)
-- ==========================================

-- 留言主表
CREATE TABLE IF NOT EXISTS messages (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    name            TEXT    NOT NULL,
    phone           TEXT    NOT NULL,
    email           TEXT    DEFAULT NULL,
    type            TEXT    NOT NULL DEFAULT '',
    company         TEXT    DEFAULT NULL,
    country         TEXT    DEFAULT NULL,
    remark          TEXT    DEFAULT NULL,
    source_url      TEXT    DEFAULT NULL,
    extra_data      TEXT    DEFAULT NULL,
    source          TEXT    NOT NULL,             -- 来源名称 = api_keys.site_name
    api_key         TEXT    NOT NULL,
    ip_address      TEXT    DEFAULT NULL,
    user_agent      TEXT    DEFAULT NULL,
    status          INTEGER DEFAULT 0,            -- 状态码关联 status_dict.code
    handle_record   TEXT    DEFAULT NULL,         -- 处理记录
    handler         TEXT    DEFAULT NULL,         -- 处理人
    created_at      TEXT    DEFAULT (datetime('now','localtime')),
    handled_at      TEXT    DEFAULT NULL,
    handled_by      TEXT    DEFAULT NULL
);

-- 索引
CREATE INDEX IF NOT EXISTS idx_source     ON messages(source);
CREATE INDEX IF NOT EXISTS idx_status     ON messages(status);
CREATE INDEX IF NOT EXISTS idx_created_at ON messages(created_at);
CREATE INDEX IF NOT EXISTS idx_handler    ON messages(handler);

-- 三奇国内官网留言（与海外独立站完全分表）
CREATE TABLE IF NOT EXISTS china_website_messages (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    inquiry_type      TEXT DEFAULT NULL,
    region            TEXT DEFAULT NULL,
    name              TEXT DEFAULT NULL,
    company           TEXT DEFAULT NULL,
    phone             TEXT DEFAULT NULL,
    email             TEXT DEFAULT NULL,
    remark            TEXT DEFAULT NULL,
    preferred_contact TEXT DEFAULT NULL,
    source_url        TEXT DEFAULT NULL,
    source            TEXT NOT NULL,
    api_key           TEXT NOT NULL,
    ip_address        TEXT DEFAULT NULL,
    user_agent        TEXT DEFAULT NULL,
    status            INTEGER DEFAULT 0,
    handle_record     TEXT DEFAULT NULL,
    handler           TEXT DEFAULT NULL,
    created_at        TEXT DEFAULT (datetime('now','localtime')),
    handled_at        TEXT DEFAULT NULL,
    handled_by        TEXT DEFAULT NULL
);
CREATE INDEX IF NOT EXISTS idx_china_status ON china_website_messages(status);
CREATE INDEX IF NOT EXISTS idx_china_created ON china_website_messages(created_at);

-- 频率限制表（防刷）
CREATE TABLE IF NOT EXISTS rate_limits (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address  TEXT    NOT NULL,
    action_type TEXT    DEFAULT 'submit',
    phone       TEXT    DEFAULT '',
    created_at  TEXT    DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS idx_rate_ip ON rate_limits(ip_address);
CREATE INDEX IF NOT EXISTS idx_rate_time ON rate_limits(created_at);

-- API Key 表 (简化：只需来源名称)
CREATE TABLE IF NOT EXISTS api_keys (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    api_key     TEXT    UNIQUE NOT NULL,
    site_name   TEXT    NOT NULL,             -- 来源名称，留言列表中显示为source
    site_scope  TEXT    DEFAULT 'overseas',   -- overseas / china
    is_active   INTEGER DEFAULT 1,
    created_at  TEXT    DEFAULT (datetime('now','localtime'))
);

-- 状态字典表
CREATE TABLE IF NOT EXISTS status_dict (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    code        INTEGER NOT NULL UNIQUE,
    name        TEXT    NOT NULL,
    color       TEXT    DEFAULT '#e8710a',
    sort_order  INTEGER DEFAULT 0,
    is_active   INTEGER DEFAULT 1
);

-- 初始化状态字典
INSERT OR IGNORE INTO status_dict (code, name, color, sort_order) VALUES (0, '新留言', '#e8710a', 1);
INSERT OR IGNORE INTO status_dict (code, name, color, sort_order) VALUES (1, '已联系', '#1a73e8', 2);
INSERT OR IGNORE INTO status_dict (code, name, color, sort_order) VALUES (2, '已成交', '#1e8e3e', 3);
INSERT OR IGNORE INTO status_dict (code, name, color, sort_order) VALUES (3, '无效', '#8892a4', 4);

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    username    TEXT    UNIQUE NOT NULL,
    password    TEXT    NOT NULL,             -- password_hash
    role        TEXT    DEFAULT 'user',       -- 'admin' / 'user'
    real_name   TEXT    DEFAULT NULL,         -- 真实姓名
    can_view_china INTEGER DEFAULT 0,          -- 国内官网留言权限
    is_active   INTEGER DEFAULT 1,
    created_at  TEXT    DEFAULT (datetime('now','localtime'))
);

-- 用户来源权限表（非管理员用户只能看指定来源的留言）
CREATE TABLE IF NOT EXISTS user_sources (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    source      TEXT    NOT NULL,             -- 可见的来源名称
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_user_sources ON user_sources(user_id);

-- 登录会话表（Token认证）
CREATE TABLE IF NOT EXISTS sessions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    token       TEXT    UNIQUE NOT NULL,
    ip_address  TEXT    DEFAULT '',
    user_agent  TEXT    DEFAULT '',
    created_at  TEXT    DEFAULT (datetime('now','localtime')),
    expires_at  TEXT    NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_sessions_token ON sessions(token);

-- ========== 初始化数据 ==========

-- 默认管理员: admin / 首次部署后请立即修改密码
INSERT OR IGNORE INTO users (id, username, password, role, real_name)
VALUES (1, 'admin', '$2y$10$Cbqllt5LGnBJfNEKJL6yLOdSWpv85Ar7lkAhHgn65WcG0NWIq6m.a', 'admin', '系统管理员');

-- API Key 请在后台按站点单独创建，不再内置可预测的默认 Key。
