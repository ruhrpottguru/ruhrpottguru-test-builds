<?php
// Standalone check (no WordPress needed): php -d output_buffering=0 tests/events_gzip_unit.php
// Loads gzip_events_response_v101191() straight from the plugin source.
ob_start();
function wp_json_encode($d){return json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
class Req{public $r;function __construct($r){$this->r=$r;}function get_route(){return $this->r;}}
class Res{public $d;public $c;function __construct($d,$c=200){$this->d=$d;$this->c=$c;}function get_data(){return $this->d;}function get_status(){return $this->c;}}
$src=file_get_contents(__DIR__.'/../wordpress/plugins/ruhrpott-guru-places/ruhrpott-guru-places.php');
$a=strpos($src,'    public function gzip_events_response_v101191');
$b=strpos($src,'    public function capture_event_feed_response');
if($a===false||$b===false||$b<=$a){fwrite(STDERR,"method not found\n");exit(1);}
eval('class T{'.substr($src,$a,$b-$a).'}');
$t=new T();
$data=['ok'=>true,'items'=>array_fill(0,500,['title'=>'Köln Ümläut','text'=>str_repeat('Beschreibung ',40)])];
$route='/ruhrpottguru/v1/events';$fails=[];
function run($t,$route,$accept,$status=200){global $data;
    if($accept===null)unset($_SERVER['HTTP_ACCEPT_ENCODING']);else $_SERVER['HTTP_ACCEPT_ENCODING']=$accept;
    ob_start();$r=$t->gzip_events_response_v101191(false,new Res($data,$status),new Req($route),null);$o=ob_get_clean();return [$r,$o];}
[$r,$o]=run($t,$route,'gzip, deflate, br');
if($r!==true||gzdecode($o)!==wp_json_encode($data))$fails[]='gzip returns identical JSON';
[$r,$o]=run($t,$route,null);if($r!==false||$o!=='')$fails[]='no Accept-Encoding';
[$r,$o]=run($t,$route,'identity');if($r!==false)$fails[]='identity only';
[$r,$o]=run($t,$route,'gzip;q=0');if($r!==false)$fails[]='gzip q=0';
[$r,$o]=run($t,$route,'br, gzip;q=0.8');if($r!==true)$fails[]='gzip q=0.8';
[$r,$o]=run($t,$route,'gzip',500);if($r!==false)$fails[]='non-200 untouched';
[$r,$o]=run($t,'/ruhrpottguru/v1/places/home','gzip');if($r!==false)$fails[]='other route untouched';
ini_set('zlib.output_compression','1');
[$r,$o]=run($t,$route,'gzip');if($r!==false)$fails[]='zlib already compressing';
echo $fails?('FAIL: '.implode(', ',$fails)):'ALL GZIP CHECKS PASSED',"\n";
exit($fails?1:0);
