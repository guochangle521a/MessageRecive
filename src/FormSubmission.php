<?php

class FormSubmission
{
    private const COMMON_EXTRA_FIELDS = ['privacy_consent','privacy_consent_at','privacy_policy_version','utm_source','utm_medium','utm_campaign','first_touch_source','conversion_source','product_key','inquiry_source'];
    private const EXTRA_FIELDS = [
        'business-inquiry' => ['industry', 'inquiry_type', 'marketing_consent'],
        'sample-request' => ['target_product', 'privacy_consent'],
        'strategic-partnership' => ['partnership_type'],
        'career-introduction' => ['expertise', 'role', 'current_position', 'experience_years', 'languages', 'preferred_location', 'strengths', 'resume_url'],
        'factory-visit' => ['visit_date', 'visitor_count', 'factory', 'privacy_consent'],
        'china-website-inquiry' => ['inquiry_type', 'region', 'preferred_contact']
    ];

    public static function handle(string $type): void
    {
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/Database.php';
        require_once __DIR__ . '/Response.php';
        require_once __DIR__ . '/Validator.php';
        require_once __DIR__ . '/Auth.php';
        require_once __DIR__ . '/RateLimiter.php';
        require_once __DIR__ . '/Logger.php';

        Response::handleCors();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            Response::error(2000, '仅支持POST请求', 405);
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            Response::error(1000, '请求体格式错误，请使用JSON格式');
        }

        Database::getInstance()->initTables();
        if ($type === 'china-website-inquiry') {
            if (!isset($data['api_key']) || !is_scalar($data['api_key']) || trim((string)$data['api_key']) === '') {
                Response::error(1001, 'api_key不能为空');
            }
        } else {
            Validator::validateMessage($data);
        }
        $key = Auth::validateApiKey($data['api_key']);
        if (!$key) Response::error(2001, 'API Key无效或已停用');
        $expectedScope = $type === 'china-website-inquiry' ? 'china' : 'overseas';
        if (($key['site_scope'] ?? 'overseas') !== $expectedScope) {
            Response::error(2001, 'API Key与当前接口不匹配');
        }

        $ip = RateLimiter::getClientIP();
        if (!RateLimiter::check($ip)) Response::error(2002, '请求过于频繁，请稍后再试', 429);
        $phone = self::text($data, 'phone');
        $targetTable = $type === 'china-website-inquiry' ? 'china_website_messages' : 'messages';
        if ($phone !== '' && !RateLimiter::checkDuplicate($ip, $phone, $targetTable)) {
            Response::error(2003, '请勿重复提交，60秒内同一电话仅可提交一次');
        }

        $clean = Validator::xss($data);
        $extra = [];
        foreach (self::EXTRA_FIELDS[$type] ?? [] as $field) {
            if (array_key_exists($field, $clean) && $clean[$field] !== '' && $clean[$field] !== null) {
                $extra[$field] = $clean[$field];
            }
        }
        foreach (self::COMMON_EXTRA_FIELDS as $field) {
            if (array_key_exists($field, $clean) && $clean[$field] !== '' && $clean[$field] !== null) $extra[$field] = $clean[$field];
        }
        if ($type !== 'china-website-inquiry') {
            self::validateOptionalFields($type, $clean);
        }

        $db = Database::getInstance();
        try {
            if ($type === 'china-website-inquiry') {
                $db->execute(
                    'INSERT INTO china_website_messages (site_id,inquiry_type,region,name,company,phone,email,remark,preferred_contact,source_url,source,api_key,ip_address,user_agent,status)
                     VALUES (:site_id,:inquiry_type,:region,:name,:company,:phone,:email,:remark,:preferred_contact,:source_url,:source,:api_key,:ip,:ua,0)',
                    [
                        ':site_id' => $key['site_id'] ?? null,
                        ':inquiry_type' => self::nullable($clean, 'inquiry_type'), ':region' => self::nullable($clean, 'region'),
                        ':name' => self::nullable($clean, 'name'), ':company' => self::nullable($clean, 'company'),
                        ':phone' => self::nullable($clean, 'phone'), ':email' => self::nullable($clean, 'email'),
                        ':remark' => self::nullable($clean, 'remark'), ':preferred_contact' => self::nullable($clean, 'preferred_contact'),
                        ':source_url' => self::nullable($clean, 'source_url'), ':source' => $key['site_name'],
                        ':api_key' => trim($data['api_key']), ':ip' => $ip, ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
                    ]
                );
                $id = $db->lastInsertId();
                Logger::log('china_submit', ['id' => $id, 'source' => $key['site_name']]);
                Response::success(['id' => (int)$id, 'type' => $type, 'submitted_at' => date('Y-m-d H:i:s')], '提交成功');
            }
            $db->execute(
                'INSERT INTO messages (site_id,type,name,phone,email,company,country,remark,source_url,extra_data,source,inquiry_source,product_key,utm_source,utm_medium,utm_campaign,first_touch_source,conversion_source,api_key,ip_address,user_agent,status)
                 VALUES (:site_id,:type,:name,:phone,:email,:company,:country,:remark,:source_url,:extra_data,:source,:inquiry_source,:product_key,:utm_source,:utm_medium,:utm_campaign,:first_touch,:conversion,:api_key,:ip,:ua,0)',
                [
                    ':site_id' => $key['site_id'] ?? null,
                    ':type' => $type, ':name' => self::text($clean, 'name'), ':phone' => self::text($clean, 'phone'),
                    ':email' => self::nullable($clean, 'email'), ':company' => self::nullable($clean, 'company'),
                    ':country' => self::nullable($clean, 'country'), ':remark' => self::nullable($clean, 'remark'),
                    ':source_url' => self::nullable($clean, 'source_url'),
                    ':extra_data' => $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    ':source' => $key['site_name'], ':inquiry_source'=>self::nullable($clean,'inquiry_source'),
                    ':product_key'=>self::nullable($clean,'product_key')??self::nullable($clean,'target_product'),
                    ':utm_source'=>self::nullable($clean,'utm_source'),':utm_medium'=>self::nullable($clean,'utm_medium'),':utm_campaign'=>self::nullable($clean,'utm_campaign'),
                    ':first_touch'=>self::nullable($clean,'first_touch_source'),':conversion'=>self::nullable($clean,'conversion_source'),
                    ':api_key' => trim($data['api_key']), ':ip' => $ip,
                    ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
                ]
            );
            $id = $db->lastInsertId();
            Logger::log('submit', ['id' => $id, 'type' => $type, 'source' => $key['site_name']]);
            Response::success(['id' => (int)$id, 'type' => $type, 'submitted_at' => date('Y-m-d H:i:s')], '提交成功');
        } catch (Throwable $e) {
            Logger::error('submit', ['error' => $e->getMessage(), 'type' => $type]);
            Response::error(5000, '服务器内部错误', 500);
        }
    }

    private static function validateOptionalFields(string $type, array $data): void
    {
        if (!empty($data['source_url']) && !filter_var(htmlspecialchars_decode($data['source_url']), FILTER_VALIDATE_URL)) {
            Response::error(1006, 'source_url格式不正确');
        }
        if ($type === 'career-introduction' && !empty($data['resume_url']) && !filter_var(htmlspecialchars_decode($data['resume_url']), FILTER_VALIDATE_URL)) {
            Response::error(1007, 'resume_url格式不正确');
        }
        if ($type === 'factory-visit' && isset($data['visitor_count']) && $data['visitor_count'] !== '') {
            $count = filter_var($data['visitor_count'], FILTER_VALIDATE_INT);
            if ($count === false || $count < 1 || $count > 30) Response::error(1008, 'visitor_count必须为1至30');
        }
    }

    private static function text(array $data, string $field): string
    {
        $value = $data[$field] ?? '';
        return is_scalar($value) ? trim((string)$value) : '';
    }
    private static function nullable(array $data, string $field) { $v = self::text($data, $field); return $v === '' ? null : $v; }
}
