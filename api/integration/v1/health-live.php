<?php
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);echo json_encode(['status'=>'method_not_allowed']);exit;}
echo json_encode(['status'=>'live','serviceVersion'=>'1.1.0','time'=>gmdate('c')]);
