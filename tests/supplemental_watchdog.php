<?php
$src=file_get_contents($argv[1]??'wordpress/plugins/ruhrpott-guru-places/ruhrpott-guru-places.php');$a=strpos($src,'    public function ensure_event_supplemental_worker()');$b=strpos($src,'    private function event_region_forensic_snapshot()',$a);$method=substr($src,$a,$b-$a);
define('MINUTE_IN_SECONDS',60);
function get_option($k,$d=false){return $GLOBALS['opt'][$k]??$d;}
function update_option($k,$v,$a=false){$GLOBALS['opt'][$k]=$v;return true;}
function current_time($f){return date('Y-m-d H:i:s');}
function sanitize_key($s){return $s;}
function wp_next_scheduled($h){return $GLOBALS['next'];}
function wp_clear_scheduled_hook($h){$GLOBALS['clears']++;$GLOBALS['next']=false;}
function wp_schedule_single_event($t,$h){$GLOBALS['scheduled'][]=$t;$GLOBALS['next']=$t;}
function spawn_cron(){$GLOBALS['spawns']++;}
eval('class WatchdogHarness { const EVENT_TIER_STATE="tier";const EVENT_SUPPLEMENTAL_BATCH_HOOK="supp";'.$method.' private function pause_event_enrichment_for_supplemental(){} private function start_event_supplemental_wave($t){$GLOBALS["newwave"]++;}}');
$h=new WatchdogHarness;$n=0;
function ok($v,$m){global $n;$n++;if(!$v)throw new Exception('FAIL '.$m);}
function setup_case($next,$age=7200,$status='running'){$GLOBALS['opt']=['rg_events_nightly_state'=>['status'=>'finished','run_token'=>'master-token'],'rg_events_supplemental_state'=>['status'=>$status,'master_run_token'=>'master-token','last_progress_at'=>date('Y-m-d H:i:s',time()-$age),'city_index'=>38,'supplemental_run_token'=>'keep-token']];$GLOBALS['next']=$next;$GLOBALS['clears']=0;$GLOBALS['scheduled']=[];$GLOBALS['spawns']=0;$GLOBALS['newwave']=0;}
// Reproduce repeated init (front end and wp-cron bootstrap) with a stale heartbeat.
$due=time()-90;setup_case($due);for($i=0;$i<5;$i++)$h->ensure_event_supplemental_worker();ok($GLOBALS['next']===$due,'overdue cron must remain due after repeated init');ok($GLOBALS['clears']===0,'existing job never cleared');ok($GLOBALS['scheduled']===[],'existing due job never postponed');ok($GLOBALS['spawns']===5,'normal WP cron requested');ok(get_option('rg_events_supplemental_state')['city_index']===38,'city cursor preserved');ok(get_option('rg_events_supplemental_state')['supplemental_run_token']==='keep-token','run token preserved');ok($GLOBALS['newwave']===0,'no new wave');
$future=time()+120;setup_case($future);$h->ensure_event_supplemental_worker();ok($GLOBALS['next']===$future,'future retry retained despite stale heartbeat');ok($GLOBALS['clears']===0,'future retry not cleared');
setup_case(false);$before=time();$h->ensure_event_supplemental_worker();ok($GLOBALS['next']>=$before&&$GLOBALS['next']<=time(),'missing cron recreated immediately due');ok(count($GLOBALS['scheduled'])===1,'one missing hook repaired');$h->ensure_event_supplemental_worker();ok(count($GLOBALS['scheduled'])===1,'repair does not duplicate hook');
setup_case(time()+120,30);$h->ensure_event_supplemental_worker();ok($GLOBALS['spawns']===0,'healthy future schedule untouched');ok($GLOBALS['clears']===0,'healthy job not cleared');
setup_case(time()-90,30);$due=$GLOBALS['next'];$h->ensure_event_supplemental_worker();ok($GLOBALS['next']===$due,'overdue job preserved even with recent heartbeat');ok($GLOBALS['spawns']===1,'overdue dispatcher requested');
setup_case(false);$o=get_option('rg_events_supplemental_state');$o['yield_for_short_refresh']=true;update_option('rg_events_supplemental_state',$o);update_option('tier',['status'=>'running']);$h->ensure_event_supplemental_worker();ok($GLOBALS['next']>=time()+119,'active ET4 wave retains wait');ok($GLOBALS['spawns']===0,'active ET4 not bypassed');
setup_case(false,7200,'finished');$h->ensure_event_supplemental_worker();ok($GLOBALS['next']===false,'finished supplemental not restarted');
setup_case(false);update_option('rg_events_nightly_state',['status'=>'running']);$h->ensure_event_supplemental_worker();ok($GLOBALS['next']===false,'active master not bypassed');
echo "$n watchdog functional assertions passed\n";
