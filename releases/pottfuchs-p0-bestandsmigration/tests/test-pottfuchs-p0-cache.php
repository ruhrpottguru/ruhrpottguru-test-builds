<?php
/* P0 v1.0.2 unit tests, stub WordPress only; no network/live database. */
error_reporting(E_ALL);
define('ABSPATH','/tmp/');
$GLOBALS['o']=array();$GLOBALS['posts']=array();$GLOBALS['actions']=array();
$GLOBALS['native_rebuilds']=0;$GLOBALS['broken_rebuild']=false;
function get_option($k,$default=false){return array_key_exists($k,$GLOBALS['o'])?$GLOBALS['o'][$k]:$default;}
function add_option($k,$v,$deprecated='',$autoload='yes'){if(isset($GLOBALS['o'][$k]))return false;$GLOBALS['o'][$k]=$v;return true;}
function update_option($k,$v,$autoload=null){$GLOBALS['o'][$k]=$v;return true;}
function delete_option($k){unset($GLOBALS['o'][$k]);return true;}
function get_post($id){return isset($GLOBALS['posts'][$id])?clone $GLOBALS['posts'][$id]:null;}
function wp_update_post($data,$err=false){$id=$data['ID'];$GLOBALS['posts'][$id]->post_content=$data['post_content'];return $id;}
function wp_slash($a){return $a;}
function is_wp_error($r){return false;}
function current_user_can($p){return true;}
function get_bloginfo($p){return 'PottFuchs';}
function home_url($path='/'){return 'https://www.pottfuchs.de'.$path;}
function wp_cache_delete($k,$g){return true;}
function esc_html($m){return (string)$m;}
function wp_die($m){throw new RuntimeException((string)$m);}
function register_activation_hook($f,$callback){$GLOBALS['activation']=$callback;}
function add_action($hook,$callback,$priority=10){$GLOBALS['actions'][]=$hook;}
function add_management_page(){}
function set_transient($key,$msg,$seconds){}
function get_transient($key){return false;}
function delete_transient($key){}
function wp_nonce_field($key){}
function submit_button($text,$type='primary'){}
function check_admin_referer($key){return true;}
function admin_url($path){return $path;}
function wp_safe_redirect($url){}
class FakeCache {
 public function cache_all_loaded_snippets(){
    $GLOBALS['native_rebuilds']++;
    if($GLOBALS['broken_rebuild'])return false;
    $cache=get_option('wpcode_snippets');
    foreach($cache['everywhere'] as &$item){
        if(isset($GLOBALS['posts'][$item['id']]))$item['code']=$GLOBALS['posts'][$item['id']]->post_content;
    }
    update_option('wpcode_snippets',$cache,false);return true;
 }
}
class FakeWPCode {public $cache;public function __construct(){$this->cache=new FakeCache();}}
function wpcode(){return new FakeWPCode();}
require dirname(__DIR__).'/pottfuchs-p0-bestandsmigration.php';
function ok($value,$name){if(!$value)throw new RuntimeException('FAIL '.$name);echo "PASS ".$name."\n";}
function fixture($id){
 if($id===3343){
    return str_repeat('// normal SEO source ',44)."rpg_seo_core_canonical "
        .str_repeat("'name'=>get_bloginfo('name'),",3)."auf PottFuchs";
 }
 return str_repeat('// correct detail SEO ',44)
   ."rpg_seo_v3_filter "
   ."\$seo_title .= ' | '.get_bloginfo('name');";
}
function setup_fixture(){
 $GLOBALS['o']=array();$GLOBALS['posts']=array();$GLOBALS['native_rebuilds']=0;$GLOBALS['broken_rebuild']=false;
 $GLOBALS['o'][pf_p0_state_record()]=array('date'=>'2026-10-26T20:01:00Z');
 foreach(array(3343,4102) as $id)$GLOBALS['posts'][$id]=(object)array(
  'ID'=>$id,'post_type'=>'wpcode','post_status'=>'publish','post_content'=>fixture($id));
 $GLOBALS['posts'][4115]=(object)array('ID'=>4115,'post_type'=>'page','post_name'=>'zeitreise-von-oben','post_status'=>'publish',
  'post_content'=>'Starte an deinem RuhrpottGuru-Standort oder suche einen Ort.');
 $GLOBALS['o']['wpcode_snippets']=array('everywhere'=>array(
  array('id'=>3343,'code'=>str_repeat('X',500)."'name'=>'RuhrpottGuru'"),
  array('id'=>4102,'code'=>str_repeat('Y',500)." | RuhrpottGuru"),
  array('id'=>90,'code'=>'Some other active rule, never altered')));
}
setup_fixture();
$old=serialize(get_option('wpcode_snippets'));$pageBefore=get_post(4115)->post_content;
ok(pf_p0_102_rebuild()===true,'native owner cache refreshed successfully');
ok($GLOBALS['native_rebuilds']===1,'calls existing WPCode cache API once');
$codes=pf_p0_102_cached(get_option('wpcode_snippets'));
ok(pf_p0_102_source_good(3343,$codes[3343])&&pf_p0_102_source_good(4102,$codes[4102]),'both owners now execute patched DB code');
ok(strpos(get_post(4115)->post_content,'PottFuchs-Standort')!==false,'existing single public Zeitreise label fixed');
ok(get_option(pf_p0_102_opt('state'),false)!==false,'one-time state and backup persisted');
ok(pf_p0_102_rebuild()===true&&$GLOBALS['native_rebuilds']===1,'repeated code cannot reload active snippets');
ok(pf_p0_102_restore()===true,'independent rollback succeeds');
ok(serialize(get_option('wpcode_snippets'))===$old,'WPCode cache is byte-preserved by rollback');
ok(get_post(4115)->post_content===$pageBefore,'Zeitreise content is byte-preserved by rollback');
ok(!get_option(pf_p0_102_opt('backup'),false),'rollback removes own backup and state');
setup_fixture();$GLOBALS['posts'][3343]->post_content=str_replace('auf PottFuchs','auf RuhrpottGuru',fixture(3343));
try{pf_p0_102_rebuild();throw new RuntimeException('Unsafe existing owner accepted');}
catch(RuntimeException $e){ok(strpos($e->getMessage(),'3343')!==false,'old unpatched owner rejects before writes');}
ok(!get_option(pf_p0_102_opt('backup'),false),'failed owner never writes backup');
setup_fixture();$GLOBALS['broken_rebuild']=true;
try{pf_p0_102_rebuild();throw new RuntimeException('Broken native cache accepted');}
catch(RuntimeException $e){ok(strpos($e->getMessage(),'nicht übernommen')!==false,'native cache failure detected');}
ok(!get_option(pf_p0_102_opt('backup'),false)&&get_post(4115)->post_content===$pageBefore,'failed reload rolls back both components');
setup_fixture();$GLOBALS['o']['wpcode_snippets']=false;
try{pf_p0_102_rebuild();throw new RuntimeException('No WPCode cache accepted');}
catch(RuntimeException $e){ok(strpos($e->getMessage(),'Preload')!==false,'missing cache correctly refused');}
setup_fixture();pf_p0_102_rebuild();$GLOBALS['posts'][4102]->post_content.='//changed';
try{pf_p0_102_restore();throw new RuntimeException('Concurrency ignored');}
catch(RuntimeException $e){ok(strpos($e->getMessage(),'inzwischen verändert')!==false,'rollback refuses later source changes');}
ok(get_option(pf_p0_102_opt('backup'),false)!==false,'rollback refusal retains rescue backup');
echo "ALL P0 CACHE REGRESSIONS PASS\n";
