<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$input=$argv[1]??(__DIR__.'/../data/geoip/dbip-country-lite-'.gmdate('Y-m').'.csv.gz');
$outDir=$argv[2]??(__DIR__.'/../data/geoip');
if(!is_file($input)){fwrite(STDERR,"GeoIP CSV gzip not found: $input\n");exit(1);}if(!is_dir($outDir)&&!mkdir($outDir,0755,true)){fwrite(STDERR,"Cannot create output directory\n");exit(1);}
$in=gzopen($input,'rb');$v4=fopen($outDir.'/country-v4.bin','wb');$v6=fopen($outDir.'/country-v6.bin','wb');if(!$in||!$v4||!$v6){fwrite(STDERR,"Cannot open GeoIP files\n");exit(1);}
$counts=[4=>0,16=>0];
while(!gzeof($in)){$row=fgetcsv($in,0,',');if(!$row||count($row)<3)continue;[$start,$end,$country]=$row;$a=@inet_pton($start);$b=@inet_pton($end);$country=strtoupper(trim($country));if($a===false||$b===false||strlen($a)!==strlen($b)||!preg_match('/^[A-Z]{2}$/',$country))continue;$width=strlen($a);if(!isset($counts[$width]))continue;fwrite($width===4?$v4:$v6,$a.$b.$country);$counts[$width]++;}
gzclose($in);fclose($v4);fclose($v6);echo "IPv4={$counts[4]} IPv6={$counts[16]}\n";
