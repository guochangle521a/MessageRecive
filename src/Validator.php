<?php
/**
 * 参数校验类
 */

class Validator {

    /**
     * 校验手机号（中国大陆）
     */
    public static function phone($value) {
        if (empty($value)) return false;
        return (bool)preg_match('/^1[3-9]\d{9}$/', $value);
    }

    /**
     * 校验邮箱
     */
    public static function email($value) {
        if (empty($value)) return true; // 邮箱可选
        return (bool)filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    /**
     * 校验必填
     */
    public static function required($value, $fieldName = '') {
        $valid = $value !== null && $value !== '';
        if (!$valid && $fieldName) {
            Response::error(1001, $fieldName . '不能为空');
        }
        return $valid;
    }

    /**
     * 校验留言提交参数
     */
    public static function validateMessage($data) {
        // 必填校验
        if (empty($data['name']) || trim($data['name']) === '') {
            Response::error(1001, '姓名不能为空');
        }
        if (empty($data['phone']) || trim($data['phone']) === '') {
            Response::error(1001, '手机号不能为空');
        }
        if (empty($data['api_key']) || trim($data['api_key']) === '') {
            Response::error(1001, 'api_key不能为空');
        }

        // 格式校验
        if (!self::phone($data['phone'])) {
            Response::error(1002, '手机号格式不正确');
        }
        if (!self::email(isset($data['email']) ? $data['email'] : '')) {
            Response::error(1003, '邮箱格式不正确');
        }

        // 长度限制
        if (mb_strlen($data['name']) > 50) {
            Response::error(1004, '姓名长度不能超过50个字符');
        }
        if (isset($data['remark']) && mb_strlen($data['remark']) > 2000) {
            Response::error(1005, '留言内容长度不能超过2000个字符');
        }

        return true;
    }

    /**
     * XSS 过滤
     */
    public static function xss($value) {
        if (is_array($value)) {
            return array_map([self::class, 'xss'], $value);
        }
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
