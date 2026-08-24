<?php
require_once __DIR__.'/../../../config/database.php';require_once __DIR__.'/../../../src/Database.php';header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);echo json_encode(['status'=>'method_not_allowed']);exit;}
try{$db=Database::getInstance();$db->initTables();$db->queryOne('SELECT 1 FROM analytics_events LIMIT 1');$db->queryOne('SELECT 1 FROM messages LIMIT 1');echo json_encode(['status'=>'ready','serviceVersion'=>'1.1.0','dependencies'=>['database'=>'ready','analytics'=>'ready','inquiries'=>'ready'],'time'=>gmdate('c')]);}catch(Throwable $e){http_response_code(503);echo json_encode(['status'=>'not_ready','time'=>gmdate('c')]);}
