<?php
/** Integration-style WordPress stubs and real archived Places source smoke. */
error_reporting(E_ALL);
define('ABSPATH','/test-wordpress/');
$base=dirname(__DIR__);
if(isset($argv[1]))$fixture=$argv[1];
else $fixture=$base.'/fixtures/ruhrpott-guru-places-v169.php';
$GLOBALS['p0_fake_posts']=array();
$GLOBALS['p0_fake_options']=array();
$GLOBALS['p0_fake_actions']=array();
$GLOBALS['p0_fake_post_failure']=false;
$GLOBALS['p0_blogname']='PottFuchs';
$GLOBALS['p0_write_calls']=0;
function register_activation_hook($file,$callback){$GLOBALS['p0_fake_activation']=$callback;}
function add_action($name,$handler){$GLOBALS['p0_fake_actions'][$name][]=$handler;}
function current_user_can($cap){return true;}
function get_bloginfo($key){return $key==='name'?$GLOBALS['p0_blogname']:'UTF-8';}
function home_url($path='/'){return 'https://www.pottfuchs.de'.$path;}
function wp_die($message){throw new RuntimeException((string)$message);}
function esc_html($str){return htmlspecialchars((string)$str,ENT_QUOTES,'UTF-8');}
function get_post($id){return $GLOBALS['p0_fake_posts'][$id]??null;}
function wp_slash($value){if(is_array($value)){foreach($value as $k=>$v)$value[$k]=wp_slash($v);return $value;}return is_string($value)?addslashes($value):$value;}
function wp_update_post($args,$wp_error=false){
 $GLOBALS['p0_write_calls']++;
 if($GLOBALS['p0_fake_post_failure'])return new WP_Error('failure');
 $id=$args['ID'];$p=get_post($id);if(!$p)return new WP_Error('no post');
 $p->post_content=stripslashes($args['post_content']);return $id;
}
class WP_Error{public $code;function __construct($code){$this->code=$code;}}
function is_wp_error($obj){return $obj instanceof WP_Error;}
function get_option($key,$default=false){return array_key_exists($key,$GLOBALS['p0_fake_options'])?$GLOBALS['p0_fake_options'][$key]:$default;}
function add_option($key,$value,$deprecated='',$autoload='no'){
 if(array_key_exists($key,$GLOBALS['p0_fake_options']))return false;
 $GLOBALS['p0_fake_options'][$key]=$value;return true;
}
function update_option($key,$value,$autoload='no'){$GLOBALS['p0_fake_options'][$key]=$value;return true;}
function delete_option($key){unset($GLOBALS['p0_fake_options'][$key]);return true;}
function set_transient($key,$value,$ttl){return true;}
function get_transient($key){return false;}
function delete_transient($key){return true;}
function admin_url($url){return 'https://www.pottfuchs.de/wp-admin/'.$url;}
function wp_safe_redirect($url){$GLOBALS['p0_last_redirect']=$url;}
function check_admin_referer($a){return true;}
function wp_nonce_field($a){return true;}
function submit_button($text,$type){return true;}
function add_management_page($page,$menu,$cap,$slug,$callback){return true;}

define('WP_PLUGIN_DIR',sys_get_temp_dir().'/pf-p0-test-'.getmypid());
$pluginFile=$base.'/pottfuchs-p0-bestandsmigration.php';
if(!is_file($pluginFile))$pluginFile=$base.'/pottfuchs-p0-bestandsmigration/pottfuchs-p0-bestandsmigration.php';
require $pluginFile;
function pf_assert($condition,$message){if(!$condition)throw new RuntimeException('TEST FAILED: '.$message);echo 'PASS: '.$message."\n";}
function pf_fixture($id){
 $x=str_repeat("/* Reference safe long PHP comment */\n",40);
 if($id===3343){
  $x.="// rpg_seo_core\nget_bloginfo('name');\n";
  $x.="\$fallback = 'auf RuhrpottGuru';\n";
  $x.="\$schema = array(array('@type'=>'Organization','name'=>'RuhrpottGuru'),array('@type'=>'WebSite','name'=>'RuhrpottGuru'));\n";
  $x.="\$crumb = array('@type'=>'ListItem','name'=>'RuhrpottGuru');\n";
  return $x;
 }
 $x.="rpg_seo_v3_event; rpg_seo_v3_place; rpg_seo_v3_filter;\n";
 $x.="\$seo_title .= ' | RuhrpottGuru';\n";
 return $x;
}
function pf_reset_post_fixtures(){
 $GLOBALS['p0_fake_posts']=array();
 foreach(array(3343,4102) as $id){
  $p=(object)array('ID'=>$id,'post_type'=>'wpcode','post_status'=>'publish','post_content'=>pf_fixture($id));
  $GLOBALS['p0_fake_posts'][$id]=$p;
 }
 $GLOBALS['p0_fake_options']=array();$GLOBALS['p0_fake_post_failure']=false;
 $GLOBALS['p0_blogname']='PottFuchs';$GLOBALS['p0_write_calls']=0;
}
$original=file_get_contents($fixture);
pf_assert(is_string($original)&&strlen($original)>100000,'actual archived Places original file available');
$patched=pf_p0_patch_places($original);
pf_assert($patched!==$original,'Places patch changes original');
pf_assert(strpos($patched,"'seo_title'=>((int)\$row['id']===16936 ? 'Zum Schwarzen Essen' : \$title)")!==false,
    'only place 16936 has exceptional SEO title');
pf_assert(substr_count($patched,"esc_html(\$seo_title.' | '.get_bloginfo('name'))")===3,'three title paths point to existing blogname');
pf_assert(strpos($patched,"'@type'=>'Place','name'=>\$title,'url'=>\$canonical")!==false,'entity schema name protected');
pf_assert(substr_count($patched,'ruhrpottguru/v1')===substr_count($original,'ruhrpottguru/v1'),'REST namespace byte-identical');
pf_assert(substr_count($patched,"wp_safe_redirect(\$canonical,301)")===substr_count($original,"wp_safe_redirect(\$canonical,301)"),'canonical redirects preserved');
pf_assert(strlen($patched)-strlen($original)<1500,'no large framework changes');
try{token_get_all($patched,TOKEN_PARSE);pf_assert(true,'entire patched PHP parses');}
catch(ParseError $e){throw new RuntimeException('patched parse failed: '.$e->getMessage());}
try{pf_p0_patch_places($patched);throw new RuntimeException('Second application wrongly allowed');}
catch(RuntimeException $e){pf_assert(strpos($e->getMessage(),'already')===false,'repatch refused (original guard)');}
$fake=array(3343=>pf_fixture(3343),4102=>pf_fixture(4102));
foreach($fake as $id=>$src){$out=pf_p0_patch_wpcode($id,$src);pf_assert($out!==$src,'WPCode '.$id.' patch modifies test owner');pf_assert(strpos($out,' | RuhrpottGuru')===false,'WPCode '.$id.' public title suffix removed');}
pf_assert(strpos(pf_p0_patch_wpcode(3343,$fake[3343]),"'name'=>get_bloginfo('name')")!==false,'brand names use existing site name');
try{pf_p0_patch_wpcode(3343,$fake[3343]."\n'name'=>'RuhrpottGuru'\n");throw new RuntimeException('Duplicate accepted');}
catch(RuntimeException $e){pf_assert(strpos($e->getMessage(),'erwartet exakt')!==false,'ambiguous source rejected');}

pf_reset_post_fixtures();
@mkdir(dirname(pf_p0_get_backend_path()),0777,true);
$testSource=$original;
pf_assert((bool)preg_match('/^\s*\* Version: 1\.10\.188\s*$/m',substr($original,0,1700)),'real version 1.10.188 original header');
pf_assert(substr_count($original,"const VERSION = '1.10.188'")===1,'real version 1.10.188 original constant');
file_put_contents(pf_p0_get_backend_path(),$testSource);
$GLOBALS['p0_fake_post_failure']=false;
call_user_func($GLOBALS['p0_fake_activation']);
pf_assert(get_option(pf_p0_state_record(),false)!==false,'activation writes state');
pf_assert(get_option(pf_p0_backup_record(),false)!==false,'activation preserves full backup');
pf_assert(strpos(get_post(3343)->post_content,"'name'=>get_bloginfo('name')")!==false,'activation updates existing WPCode 3343');
pf_assert(strpos(get_post(4102)->post_content,"' | '.get_bloginfo('name')")!==false,'activation updates existing WPCode 4102');
pf_assert(strpos(file_get_contents(pf_p0_get_backend_path()),"'seo_title'=>((int)\$row['id']===16936")!==false,'activation patches existing Places file');
$fileAfterActivation=file_get_contents(pf_p0_get_backend_path());
$codeAfterActivation=get_post(3343)->post_content;
call_user_func($GLOBALS['p0_fake_activation']);
pf_assert(file_get_contents(pf_p0_get_backend_path())===$fileAfterActivation && get_post(3343)->post_content===$codeAfterActivation,
    'second activation is a harmless no-op for successful v1.0.1 migration');

// Rollback must refuse later edits rather than silently overwrite owner changes.
$GLOBALS['p0_fake_posts'][4102]->post_content .= "\n// concurrent operator edit";
try{pf_p0_restore_backup();throw new RuntimeException('Rollback wrongly overwrote concurrent changes');}
catch(RuntimeException $e){pf_assert(strpos($e->getMessage(),'zwischenzeitlich veraendert')!==false,
   'rollback refuses post-install snippet changes');}
$GLOBALS['p0_fake_posts'][4102]->post_content=pf_p0_patch_wpcode(4102,pf_fixture(4102));
pf_assert(pf_p0_restore_backup()===true,'administrator rollback operation succeeds');
pf_assert(file_get_contents(pf_p0_get_backend_path())===$testSource,'successful rollback restores exact file bytes');
pf_assert(get_post(3343)->post_content===pf_fixture(3343),'successful rollback restores WPCode 3343');
pf_assert(get_post(4102)->post_content===pf_fixture(4102),'successful rollback restores WPCode 4102');
pf_assert(get_option(pf_p0_backup_record(),false)===false,'successful rollback clears stored backup');
pf_assert(get_option(pf_p0_state_record(),false)===false,'successful rollback clears one-time run state');

pf_reset_post_fixtures();
file_put_contents(pf_p0_get_backend_path(),$testSource);
$GLOBALS['p0_fake_post_failure']=true;
try{call_user_func($GLOBALS['p0_fake_activation']);throw new RuntimeException('Failure injection unexpectedly succeeded');}
catch(RuntimeException $e){
 pf_assert(strpos($e->getMessage(),'Alle Aenderungen zurueckgerollt')!==false,'failure triggers rollback');
 pf_assert(file_get_contents(pf_p0_get_backend_path())===$testSource,'failed activation restores exact Places bytes');
 pf_assert(get_post(3343)->post_content===pf_fixture(3343),'failed activation restores exact WPCode bytes');
}

pf_reset_post_fixtures();
file_put_contents(pf_p0_get_backend_path(),$testSource);
$GLOBALS['p0_blogname']='RuhrpottGuru';
try{call_user_func($GLOBALS['p0_fake_activation']);throw new RuntimeException('Wrong domain accepted');}
catch(RuntimeException $e){pf_assert(strpos($e->getMessage(),'Webseite')!==false,'mismatched site title aborts before writes');}

pf_reset_post_fixtures();
$wrongVersion=preg_replace('/^\s*\* Version: 1\.10\.188\s*$/m',' * Version: 1.10.185',$original,1,$replacementCount);
pf_assert($replacementCount===1,'old version fixture generated');
file_put_contents(pf_p0_get_backend_path(),$wrongVersion);
try{call_user_func($GLOBALS['p0_fake_activation']);throw new RuntimeException('Wrong version accepted');}
catch(RuntimeException $e){pf_assert(strpos($e->getMessage(),'Live-Backend')!==false,'historical version rejected before writes');}

// Refuse a source that claims 1.10.188 in its header but an older app constant.
pf_reset_post_fixtures();
$wrongConst=str_replace("const VERSION = '1.10.188'","const VERSION = '1.10.185'",$original,$constCount);
pf_assert($constCount===1,'wrong constant fixture generated');
file_put_contents(pf_p0_get_backend_path(),$wrongConst);
try{call_user_func($GLOBALS['p0_fake_activation']);throw new RuntimeException('Wrong constant accepted');}
catch(RuntimeException $e){
 pf_assert(strpos($e->getMessage(),'Versionskonstante')!==false,'wrong application constant rejected before writes');
 pf_assert(get_option(pf_p0_backup_record(),false)===false,'wrong constant generated no backup');
 pf_assert($GLOBALS['p0_write_calls']===0,'wrong constant caused no WPCode write');
}
// Remove fixture copy only, never actual WordPress files.
@unlink(pf_p0_get_backend_path());@rmdir(dirname(pf_p0_get_backend_path()));@rmdir(WP_PLUGIN_DIR);
echo "PASS: PottFuchs P0 archived-code regression complete.\n";