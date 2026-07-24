<?php
/**
 * 统一 JSON 响应类
 */

class Response {

    /**
     * 成功响应
     */
    public static function success($data = null, $msg = 'ok') {
        self::json(200, ['code' => 0, 'msg' => $msg, 'data' => $data]);
    }

    /**
     * 失败响应
     */
    public static function error($code, $msg, $httpCode = 400) {
        self::json($httpCode, ['code' => $code, 'msg' => $msg, 'data' => null]);
    }

    /**
     * 分页列表响应
     */
    public static function paginate($list, $total, $page, $pageSize, $msg = 'ok') {
        self::json(200, [
            'code' => 0,
            'msg'  => $msg,
            'data' => [
                'list'      => $list,
                'total'     => (int)$total,
                'page'      => (int)$page,
                'page_size' => (int)$pageSize,
                'total_pages' => (int)ceil($total / $pageSize)
            ]
        ]);
    }

    /**
     * 输出 JSON
     */
    private static function json($httpCode, $data) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: ' . CORS_ORIGINS);
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * 处理 OPTIONS 预检请求
     */
    public static function handleCors() {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Origin: ' . CORS_ORIGINS);
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            header('Access-Control-Max-Age: 86400');
            http_response_code(204);
            exit;
        }
    }
}
