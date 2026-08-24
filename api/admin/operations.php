<?php
require_once __DIR__.'/../../config/database.php';require_once __DIR__.'/../../src/Database.php';require_once __DIR__.'/../../src/Response.php';require_once __DIR__.'/../../src/Auth.php';
Response::handleCors();$db=Database::getInstance();$db->initTables();Auth::requireAdmin();
$sites=$db->query("SELECT s.id,s.name,s.domain,(SELECT MAX(received_at) FROM analytics_events e WHERE e.site_id=s.id) last_event_at,(SELECT MAX(data_as_of) FROM daily_site_metrics d WHERE d.site_id=s.id) last_aggregate_at,(SELECT COUNT(*) FROM analytics_events e WHERE e.site_id=s.id AND e.is_bot=0 AND e.received_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 24 HOUR)) events_24h,(SELECT COUNT(*) FROM analytics_events e WHERE e.site_id=s.id AND e.is_bot=1 AND e.received_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 24 HOUR)) bots_24h FROM sites s ORDER BY s.id");
$storage=$db->queryOne("SELECT COUNT(*) raw_events,MIN(occurred_at) oldest_event,MAX(occurred_at) newest_event FROM analytics_events");
Response::success(['sites'=>$sites,'storage'=>$storage,'retention_days'=>RAW_EVENT_RETENTION_DAYS,'session_timeout_seconds'=>TRACK_SESSION_TIMEOUT,'server_time_utc'=>gmdate('c')]);
