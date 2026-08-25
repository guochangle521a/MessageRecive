<?php
class Analytics {
    public static function maskIp($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) { $p=explode('.',$ip); return $p[0].'.'.$p[1].'.*.*'; }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) { $p=explode(':',$ip); return implode(':',array_slice($p,0,3)).'::*'; }
        return null;
    }
    public static function visitorHash($siteId, $ip, $day) { return hash_hmac('sha256', $siteId.'|'.$day.'|'.$ip, VISITOR_HASH_SECRET); }
    public static function isBot($ua) {
        if ($ua === '') return true;
        return (bool)preg_match('/bot|crawler|spider|slurp|headless|phantom|wget|curl|monitor|preview|lighthouse/i', $ua);
    }
    public static function device($ua) {
        if (preg_match('/tablet|ipad/i',$ua)) return 'tablet';
        if (preg_match('/mobile|iphone|android/i',$ua)) return 'mobile';
        return 'desktop';
    }
    public static function source($referrer) {
        if (!$referrer) return 'direct';
        $host = strtolower(parse_url($referrer, PHP_URL_HOST) ?: 'unknown');
        foreach (['google','bing','baidu','yahoo','yandex'] as $search) if (strpos($host,$search)!==false) return 'organic';
        return 'referral';
    }
    public static function sourceFromUtm($source,$medium='') {
        $source=strtolower(trim((string)$source)); $medium=strtolower(trim((string)$medium));
        if($source==='') return 'unknown';
        if(preg_match('/google|bing|baidu|yahoo|yandex/',$source)) return preg_match('/cpc|ppc|paid/',$medium)?'paid_search':$source;
        if(preg_match('/linkedin|facebook|instagram|youtube|twitter|x\.com|wechat|weixin|tiktok/',$source)) return 'social';
        if(preg_match('/chatgpt|openai|perplexity|claude|gemini|copilot|deepseek/',$source)) return 'ai_referral';
        if(preg_match('/partner|affiliate|distributor/',$medium.' '.$source)) return 'partner';
        return preg_replace('/[^a-z0-9_-]+/','_',substr($source,0,80))?:'other';
    }
}
