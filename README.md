# 三奇统一询盘与网站数据平台

一期范围：海外独立站 `www.sanqi.cn`、国内官网 `www.sanqicn.com`。GEO 不在本期范围。

## 运行要求

- PHP 8.2+：PDO、pdo_mysql、mbstring、JSON
- MySQL 8.0+
- Web 根目录指向本项目目录
- 数据库存 UTC，管理后台按北京时间展示

复制 `config/local.php`（该文件已被 Git 忽略）或设置环境变量：

```text
SANQI_DB_HOST SANQI_DB_PORT SANQI_DB_NAME SANQI_DB_USER SANQI_DB_PASS
SANQI_VISITOR_SECRET SANQI_CORS_ORIGINS
```

首次请求会执行 `sql/init.sql`。生产环境应使用独立数据库账号，并立即设置管理员强密码。

## tracker.js 接入

在网站所有页面的 `</body>` 前加入：

```html
<script async
  src="https://message.sanqifz.com:38038/public/tracker.js"
  data-site-key="后台网站管理中显示的公开采集Key"></script>
```

公开采集 Key 不是后台密钥。采集接口还会验证请求来源域名。PV 每次页面加载计数；UV 使用“网站＋UTC日期＋IP”的加盐哈希去重；会话超时为 180 秒。完整 IP 保存在内部会话表并仅供有相应网站权限的后台用户查看，第三方只读接口不返回 IP。

V1.1 Tracker 同时采集经过清洗的 `utm_source`、`utm_medium`、`utm_campaign`、引荐域名、页面语言和着陆页。产品页可增加 `<meta name="sanqi-product-key" content="固定产品编码">`，或在统计脚本上增加 `data-product-key`。首次／转化归因可由 BFF 读取 `window.SanqiAnalytics.getAttribution()` 后随留言提交。

国家代码优先读取可信代理提供的国家头，否则使用服务器本地IP国家二进制库匹配；不会向第三方查询访客IP。使用 `php scripts/build-geoip.php data/geoip/dbip-country-lite-YYYY-MM.csv.gz` 生成运行文件。IP国家数据来自 DB-IP Lite（CC BY 4.0），匹配失败、内网和保留地址统一记为 `unknown`。

## 网站访问明细后台

“网站数据中心 → 访问与会话明细”提供四类内部查询：

- 访问事件：逐条查看访问时间、页面、来源、设备、完整 IP、访客标识、会话及机器人判断。
- 会话明细：查看入口页面、来源、持续时间、完整 IP 和完整访问路径。
- 页面分析：查看页面 PV、UV、会话、每日趋势及最近访问记录。
- 访客分析：按匿名访客哈希查看完整 IP、首次/最近访问、会话和浏览页面。

旧版本只保存脱敏 IP 的历史记录无法还原；升级后的新采集记录开始保存完整 IP。完整 IP 属于内部数据，不得通过第三方 API、导出文件或公开日志对外提供。

## 定时聚合

建议宝塔计划任务每 5 分钟执行：

```bash
php /网站目录/scripts/aggregate.php
```

可选参数：`php scripts/aggregate.php 2026-08-01 2026-08-22`。任务同时删除超过 90 天的原始事件，聚合数据长期保留。

## 第三方只读接口

业务接口使用独立 Bearer Token，可限制网站、权限、出口 IP 和到期时间，不复用表单 API Key。接口只返回聚合数据，不返回姓名、电话、邮箱、留言正文、完整 IP 或 Cookie。

V1.1 统一响应增加 `traceId`、`serviceVersion`、`sourceSiteId`、`freshnessSlaSeconds` 和对规范化 `data` 计算的 `sourceDigest`。稳定站点标识为 `sanqi-overseas`。当前正式服务继续使用端口 `38038`，不提供 443 入口。

### 调用准备

正式环境基础地址：

```text
https://message.sanqifz.com:38038
```

`health-live.php` 和 `health-ready.php` 不需要 Token。其余接口必须在请求头携带后台“第三方 API 客户端”中创建的 Bearer Token：

管理员也可以进入“接口与集成 → 第三方 API 客户端 → 接口调用调试”，在后台选择接口并查看实际 HTTP 状态、耗时和 JSON 返回。调试框中的 Token 只在当前页面内存中使用，不会写入数据库或浏览器存储。

Bearer Token 生成方法：进入“接口与集成 → 第三方 API 客户端”，点击“新建客户端”，填写客户端名称，选择授权网站和只读权限，并按需设置出口 IP 白名单及到期日期。点击“生成 Bearer Token”后，系统只显示一次以 `sqi_` 开头的 Token；数据库仅保存 SHA-256 哈希。Token 遗失时不能找回，应停用旧客户端后重新生成。客户端不提供删除功能，以保留审计记录。

```http
Authorization: Bearer sqi_第三方客户端Token
Accept: application/json
```

以下示例使用环境变量，避免将 Token 直接写进脚本或命令历史：

```bash
export SANQI_API_BASE="https://message.sanqifz.com:38038"
export SANQI_API_TOKEN="sqi_替换为实际Token"
```

如果客户端已在后台绑定网站，业务接口自动使用该网站，不需要传 `siteId`；没有绑定网站时必须传 `siteId`。当前站点编号可从 `manifest.php` 的 `siteId` 获取。

### 1. 服务存活检查

接口：`GET /api/integration/v1/health-live.php`

只判断 PHP 接口服务是否能够响应，不检查数据库。

```bash
curl -sS "$SANQI_API_BASE/api/integration/v1/health-live.php"
```

返回示例：

```json
{
  "status": "live",
  "time": "2026-08-22T12:30:00+00:00"
}
```

正常时 HTTP 状态码为 `200`。

### 2. 服务就绪检查

接口：`GET /api/integration/v1/health-ready.php`

同时检查 PHP 服务和数据库连接，适合负载均衡、监控系统及上线验收。

```bash
curl -sS "$SANQI_API_BASE/api/integration/v1/health-ready.php"
```

正常返回：

```json
{
  "status": "ready",
  "time": "2026-08-22T12:30:00+00:00"
}
```

数据库不可用时返回 HTTP `503`：

```json
{
  "status": "not_ready"
}
```

### 3. 接口能力清单

接口：`GET /api/integration/v1/manifest.php`

```bash
curl -sS \
  -H "Authorization: Bearer $SANQI_API_TOKEN" \
  -H "Accept: application/json" \
  "$SANQI_API_BASE/api/integration/v1/manifest.php"
```

返回示例：

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "requestId": "a1b2c3d4e5f60708",
    "systemId": "sanqi-data-platform",
    "contractVersion": "v1",
    "sourceGeneratedAt": "2026-08-22T12:30:00+00:00",
    "dataAsOf": "2026-08-22T12:30:00+00:00",
    "definitionVersion": "1.0",
    "isStale": false,
    "data": {
      "endpoints": ["site-metrics", "content-status", "inquiry-summary"],
      "maxRangeDays": 90,
      "timezone": "UTC",
      "siteId": 1
    }
  }
}
```

### 4. 网站统计指标

接口：`GET /api/integration/v1/site-metrics.php`

权限：`metrics.read`

查询参数：

- `start`：开始日期，格式 `YYYY-MM-DD`，默认最近30天。
- `end`：结束日期，格式 `YYYY-MM-DD`，默认当天。
- `siteId`：未绑定网站的客户端必传；已绑定客户端忽略该参数。
- 日期范围最多90天。

```bash
curl -sS \
  -H "Authorization: Bearer $SANQI_API_TOKEN" \
  -H "Accept: application/json" \
  "$SANQI_API_BASE/api/integration/v1/site-metrics.php?start=2026-08-01&end=2026-08-22"
```

返回示例：

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "requestId": "b2c3d4e5f6070819",
    "systemId": "sanqi-data-platform",
    "contractVersion": "v1",
    "sourceGeneratedAt": "2026-08-22T12:30:00+00:00",
    "dataAsOf": "2026-08-22T12:30:00+00:00",
    "definitionVersion": "1.0",
    "isStale": false,
    "data": [
      {
        "metric_date": "2026-08-22",
        "pv": 1280,
        "uv": 736,
        "sessions": 812,
        "bot_events": 43,
        "data_as_of": "2026-08-22 12:25:00"
      }
    ]
  }
}
```

### 5. 页面访问活动与 CMS 状态

页面访问活动接口：`GET /api/integration/v1/page-activity.php`，权限为 `content.read`。支持 `start`、`end`、`limit` 和稳定游标 `cursor`，返回页面路径、标题、首次／最后访问时间、PV、UV 和 Sessions。

```bash
curl -sS -H "Authorization: Bearer $SANQI_API_TOKEN" \
  "$SANQI_API_BASE/api/integration/v1/page-activity.php?start=2026-08-01&end=2026-08-22&limit=500"
```

CMS 内容状态接口：`GET /api/integration/v1/content-status.php`。

接口：`GET /api/integration/v1/content-status.php`

权限：`content.read`

目前没有接入权威 CMS 数据源，因此该接口明确返回 `capabilityAvailable=false` 和空 `items`；不能把 Tracker 页面访问数据称为已发布内容。

```bash
curl -sS \
  -H "Authorization: Bearer $SANQI_API_TOKEN" \
  -H "Accept: application/json" \
  "$SANQI_API_BASE/api/integration/v1/content-status.php"
```

返回示例：

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "requestId": "c3d4e5f60708192a",
    "traceId": "c3d4e5f60708192a",
    "systemId": "sanqi-overseas-site",
    "sourceSiteId": "sanqi-overseas",
    "contractVersion": "v1.1",
    "sourceGeneratedAt": "2026-08-22T12:30:00+00:00",
    "dataAsOf": "2026-08-22T12:30:00+00:00",
    "definitionVersion": "1.1",
    "isStale": false,
    "capabilityAvailable": false,
    "data": {
      "reason": "authoritative_cms_not_connected",
      "items": []
    }
  }
}
```

### 6. 询盘汇总

接口：`GET /api/integration/v1/inquiry-summary.php`

权限：`inquiries.summary`

只返回人工判断后的数量汇总，不返回任何联系人资料或留言内容。

查询参数：

- `start`：开始日期，格式 `YYYY-MM-DD`，默认最近30天。
- `end`：结束日期，格式 `YYYY-MM-DD`，默认当天。
- `siteId`：未绑定网站的客户端必传。

```bash
curl -sS \
  -H "Authorization: Bearer $SANQI_API_TOKEN" \
  -H "Accept: application/json" \
  "$SANQI_API_BASE/api/integration/v1/inquiry-summary.php?start=2026-08-01&end=2026-08-22"
```

返回示例：

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "requestId": "d4e5f60708192a3b",
    "systemId": "sanqi-data-platform",
    "contractVersion": "v1",
    "sourceGeneratedAt": "2026-08-22T12:30:00+00:00",
    "dataAsOf": "2026-08-22T12:30:00+00:00",
    "definitionVersion": "1.0",
    "isStale": false,
    "data": {
      "total": 36,
      "valid_count": 18,
      "spam_count": 7,
      "pending_count": 11
    }
  }
}
```

### 错误返回

缺少或使用无效 Token 时返回 HTTP `401`：

```json
{
  "code": 401,
  "msg": "访问凭据无效",
  "data": null
}
```

出口 IP 不在白名单或客户端缺少接口权限时返回 HTTP `403`：

```json
{
  "code": 403,
  "msg": "客户端权限不足",
  "data": null
}
```

参数错误返回 HTTP `400`。调用方应记录 HTTP 状态码、`code`、`msg` 和成功响应中的 `requestId`，但不得记录完整 Bearer Token。

## 上线前

1. 更换 FTP、后台和所有已暴露凭据。
2. 设置生产数据库密码及随机 `SANQI_VISITOR_SECRET`。
3. 配置 HTTPS、CORS 白名单、第三方出口 IP 白名单及宝塔定时任务。
4. 删除本地测试数据和本地测试客户端。
5. 先影子采集并核对 7—14 天，再开放第三方接口。
