<?php
error_reporting(E_ALL);
define('ABSPATH','/');define('DAY_IN_SECONDS',86400);
$GLOBALS['opts']=['rpg_geoapify_api_key'=>'existing-project-key','pottfuchs_address_display_settings'=>['enabled'=>1]];
$GLOBALS['cache']=[]; $GLOBALS['remote']=[];
function add_action(...$args){}function add_filter(...$args){}function get_option($k,$d=null){return $GLOBALS['opts'][$k]??$d;}function update_option($k,$v){$GLOBALS['opts'][$k]=$v;return true;}function get_transient($k){return $GLOBALS['cache'][$k]??false;}function set_transient($k,$v,$ttl){$GLOBALS['cache'][$k]=$v;return true;}
function register_setting(...$args){} function register_rest_route(...$args){} function rest_ensure_response($v){return new WP_REST_Response($v);}function is_wp_error($v){return false;}function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}function wp_remote_get($u,$opts){$GLOBALS['remote'][]=$u;return ['response'=>['code'=>200],'body'=>json_encode(['results'=>[['street'=>'Hohenzollernstraße','housenumber'=>'5','postcode'=>'45128','city'=>'Essen','result_type'=>'building','rank'=>['confidence'=>0.99],'distance'=>3]]])];}function wp_remote_retrieve_response_code($r){return $r['response']['code'];}function wp_remote_retrieve_body($r){return $r['body'];}function sanitize_text_field($s){return trim((string)$s);}function sanitize_key($s){return preg_replace('/[^a-z0-9_\\-]/','',strtolower($s));}
class WP_REST_Request {private $route;private $values=[];function __construct($method='GET',$route=''){ $this->route=$route;}function set_param($k,$v){$this->values[$k]=$v;}function get_param($k){return $this->values[$k]??null;}function get_route(){return $this->route;}}
class WP_REST_Response {private $data;function __construct($d,$status=200){$this->data=$d;}function get_data(){return $this->data;}function set_data($d){$this->data=$d;}}
class WP_Error {function __construct(...$args){}}
require __DIR__.'/../wordpress/plugins/pottfuchs-address-display/pottfuchs-address-display.php';
function check($ok,$msg){if(!$ok){fwrite(STDERR,"FAIL: $msg\n");exit(1);}echo "PASS: $msg\n";}
$r=new WP_REST_Request('POST','/ruhrpottguru/v1/events');
$src=new WP_REST_Response(['items'=>[['id'=>101,'address'=>'45128 Essen','city'=>'Essen','geo'=>['latitude'=>51.445,'longitude'=>7.008]],['id'=>102,'address'=>'Hohenzollernstraße 5, 45128 Essen','city'=>'Essen','geo'=>['latitude'=>51.445,'longitude'=>7.008]],['id'=>103,'cityScope'=>true,'city'=>'Essen','geo'=>['lat'=>51.445,'lng'=>7.008]]]]);
$out=PottFuchs_Address_Display::enrich_response($src,null,$r)->get_data();
check($out['items'][0]['address']==='Hohenzollernstraße 5, 45128 Essen','city-only replaced with verified postal address');
check($out['items'][1]['address']==='Hohenzollernstraße 5, 45128 Essen','existing full address unchanged');
check(!isset($out['items'][2]['address']),'city-scope not enriched');
check(count($GLOBALS['remote'])===1,'single reverse geocoding call');
check(strpos($GLOBALS['remote'][0],'api-eu.geoapify.com')!==false,'EU endpoint');
check(strpos($GLOBALS['remote'][0],'existing-project-key')!==false,'existing Geoapify key reused');
PottFuchs_Address_Display::enrich_response(new WP_REST_Response(['items'=>[['city'=>'Essen','address'=>'45128 Essen','coords'=>['lat'=>51.445,'lng'=>7.008]]]]),null,$r);
check(count($GLOBALS['remote'])===1,'cache reused');
$unrelated=new WP_REST_Request('GET','/wp/v2/posts');PottFuchs_Address_Display::enrich_response(new WP_REST_Response(['items'=>[['geo'=>['lat'=>51.445,'lng'=>7.008]]]]),null,$unrelated);
check(count($GLOBALS['remote'])===1,'unrelated REST endpoint not changed');
$GLOBALS['opts']['pottfuchs_address_display_settings']=['enabled'=>0];PottFuchs_Address_Display::enrich_response(new WP_REST_Response(['items'=>[['coords'=>['lat'=>51.45,'lng'=>7.01]]]]),null,$r);
check(count($GLOBALS['remote'])===1,'disabled setting prevents calls');
check(is_array(PottFuchs_Address_Display::sanitize(['enabled'=>1])),'settings sanitation');
echo "All tests passed\n";
