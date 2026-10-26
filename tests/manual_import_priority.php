<?php
// Execute the real production methods with an in-memory WordPress option/cron adapter.
$path=$argv[1]??'wordpress/plugins/ruhrpott-guru-places/ruhrpott-guru-places.php';
$source=file_get_contents($path);
function method_source($src,$name){$start=strpos($src,'    private function '.$name.'(');if($start===false)$start=strpos($src,'    public function '.$name.'(');if($start===false)throw new Exception($name);$end=strpos($src,"\n    }",$start);return substr($src,$start,$end+6-$start);}
$pending=method_source($source,'event_supplemental_short_refresh_pending_v101182');
$yield=method_source($source,'event_supplemental_yield_v101182');
$worker=method_source($source,'cron_events_supplemental_refresh_batch');
$worker=substr($worker,0,strpos($worker,'        $this->pause_event_enrichment_for_supplemental();')).'        $GLOBALS["proceed"]=true; }';
define('MINUTE_IN_SECONDS',60);
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}
function update_option($key,$value,$autoload=false){$GLOBALS['options'][$key]=$value;return true;}
function wp_clear_scheduled_hook($hook){$GLOBALS['cron'][$hook]=[];}
function wp_schedule_single_event($when,$hook){$GLOBALS['cron'][$hook][]=$when;}
function current_time($format){return '2026-10-26 17:00:00';}
function spawn_cron(){}
eval('class PriorityHarness { const EVENT_TIER_QUEUE="queue";const EVENT_TIER_STATE="tier";const EVENT_SUPPLEMENTAL_BATCH_HOOK="supp";const EVENT_COVERAGE_HOOK="coverage"; '.$pending.$yield.$worker.'
public function pending(){return $this->event_supplemental_short_refresh_pending_v101182();}
private function event_import_lock_v101101(){return get_option("lock",[]);}
private function event_import_lock_release_v101101($owner){if((get_option("lock",[])["owner"]??"")!==$owner)return false;update_option("lock",[]);return true;}
private function event_import_lock_acquire_v101101($owner){if(get_option("lock",[]))return false;update_option("lock",["owner"=>$owner]);return true;}
private function event_import_lock_touch_v101101($owner){}
private function event_sync_schedule_dispatch_v101101($delay){$GLOBALS["dispatch"]=true;}
private function event_coverage_wesel_state_v101159(){return [];}
}');
$h=new PriorityHarness;$count=0;
function check($ok,$message){global $count;$count++;if(!$ok)throw new Exception("FAIL: $message");}
function reset_state($manual,$selected,$queue){$GLOBALS['options']=['rg_events_operator_manual_mode_v101185'=>$manual,'rg_events_operator_selected_tier_v101185'=>$selected,'queue'=>$queue,'rg_events_supplemental_state'=>['status'=>'running','city_index'=>33,'run_token'=>'existing-token','yield_for_short_refresh'=>true]];$GLOBALS['cron']=[];$GLOBALS['proceed']=false;$GLOBALS['dispatch']=false;}
foreach([
 [false,'',[],false], [false,'',['hot14'=>[]],true], [false,'',['plan90'=>[]],true], [false,'',['full365'=>[]],false],
 [true,'',['hot14'=>[],'plan90'=>[]],false], [true,'hot14',['hot14'=>[]],true], [true,'plan90',['plan90'=>[]],true],
 [true,'hot14',['plan90'=>[]],false], [true,'full365',['hot14'=>[],'full365'=>[]],false], [true,'unknown',['hot14'=>[]],false],
 [true,'',false,false], [false,'',false,false]
] as [$manual,$selected,$queue,$expected]){reset_state($manual,$selected,$queue);check($h->pending()===$expected,'pending '.json_encode([$manual,$selected,$queue]));}
reset_state(true,'',['plan90'=>[]]);$h->cron_events_supplemental_refresh_batch();check($GLOBALS['proceed'],'manual unselected plan must not starve supplemental');check(get_option('rg_events_supplemental_state')['city_index']===33,'cursor preserved');check(get_option('rg_events_supplemental_state')['run_token']==='existing-token','token preserved');check(!get_option('rg_events_supplemental_state')['yield_for_short_refresh'],'stale yield cleared');check(get_option('queue')===['plan90'=>[]],'queued plan retained');
foreach([[true,'hot14','hot14'],[true,'plan90','plan90'],[false,'','hot14'],[false,'','plan90']] as [$manual,$selected,$tier]){reset_state($manual,$selected,[$tier=>[]]);$h->cron_events_supplemental_refresh_batch();check(!$GLOBALS['proceed'],'selected/automatic short tier retains priority');check($GLOBALS['dispatch'],'dispatcher scheduled');}
reset_state(true,'',[]);update_option('tier',['status'=>'running']);$h->cron_events_supplemental_refresh_batch();check(!$GLOBALS['proceed'],'active short import never bypassed');check(!empty($GLOBALS['cron']['supp']),'active import reschedules supplemental');
reset_state(true,'',[]);update_option('lock',['owner'=>'other']);$h->cron_events_supplemental_refresh_batch();check(!$GLOBALS['proceed'],'foreign lock protected');check(get_option('lock')['owner']==='other','foreign lock retained');
reset_state(true,'',[]);update_option('rg_events_supplemental_operator_pause_v101183',true);$h->cron_events_supplemental_refresh_batch();check(!$GLOBALS['proceed'],'operator pause protected');check(get_option('rg_events_supplemental_state')['operator_paused'],'pause persisted');
reset_state(true,'hot14',['hot14'=>[]]);$s=get_option('rg_events_supplemental_state');$s['inflight_city']='Duisburg';update_option('rg_events_supplemental_state',$s);$h->cron_events_supplemental_refresh_batch();check($GLOBALS['proceed'],'inflight city not interrupted by queued tier');check(!$GLOBALS['dispatch'],'no yield during inflight city');
echo "$count functional assertions passed\n";
