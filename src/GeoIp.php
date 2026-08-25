<?php
class GeoIp {
    private const IPV4_RECORD_SIZE=10;
    private const IPV6_RECORD_SIZE=34;
    public static function countryCode($ip) {
        if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) return null;
        $packed=@inet_pton($ip);if($packed===false)return null;
        $v4=strlen($packed)===4;$file=__DIR__.'/../data/geoip/'.($v4?'country-v4.bin':'country-v6.bin');
        $size=$v4?self::IPV4_RECORD_SIZE:self::IPV6_RECORD_SIZE;$width=$v4?4:16;
        if(!is_file($file)||($bytes=filesize($file))<$size)return null;
        $fh=@fopen($file,'rb');if(!$fh)return null;$low=0;$high=intdiv($bytes,$size)-1;
        try{
            while($low<=$high){
                $mid=intdiv($low+$high,2);if(fseek($fh,$mid*$size)!==0)break;$record=fread($fh,$size);if(strlen($record)!==$size)break;
                $start=substr($record,0,$width);$end=substr($record,$width,$width);
                if(strcmp($packed,$start)<0){$high=$mid-1;continue;}
                if(strcmp($packed,$end)>0){$low=$mid+1;continue;}
                $code=strtoupper(substr($record,$width*2,2));return preg_match('/^[A-Z]{2}$/',$code)?$code:null;
            }
        }finally{fclose($fh);}
        return null;
    }
}
