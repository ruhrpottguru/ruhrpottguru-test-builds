<?php
if (!defined('ABSPATH')) exit;

/**
 * RPG IGA 2027 Intelligence – dark-launch classifier.
 * Additive only: reads existing event/raw/source data and writes its own claims/snapshots.
 */
final class RuhrpottGuru_IGA_Intelligence {
    const CLASSIFIER_VERSION = 'iga-event-1.0';
    const RESOLVER_VERSION = 'iga-membership-1.0';
    const WORKER_HOOK = 'rg_iga_intelligence_batch_v101081';
    const STATE_OPTION = 'rg_intelligence_iga_state';
    const LOCK_OPTION = 'rg_intelligence_iga_lock';
    const BATCH_SIZE = 250;
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'ensure_registry'), 18);
        add_action(self::WORKER_HOOK, array($this, 'run_batch'));
        add_action('admin_post_rg_iga_intelligence_start', array($this, 'admin_start'));
        add_action('admin_post_rg_iga_intelligence_override', array($this, 'admin_override'));
    }

    private function table($suffix) { global $wpdb; return $wpdb->prefix . 'rg_' . $suffix; }

    public function ensure_registry() {
        global $wpdb;
        $table=$this->table('source_registry');
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table)) !== $table) return;
        $now=current_time('mysql');
        $sources=array(
            'ET4_RUHR_MASTER'=>array('ET4 / Open Data Finder Ruhr-Master','event_feed','et4_event_master','event_sources','scheduled'),
            'DESTINATION_ONE'=>array('destination.one Zusatzfeed','event_feed','event_supplemental','event_sources','scheduled'),
            'IGA_OFFICIAL'=>array('IGA 2027 Ruhrgebiet · offizielle Quelle','official_iga','iga_official','','milestone'),
            'RPG_EDITORIAL'=>array('RuhrpottGuru Redaktion','editorial','rpg_editorial','intelligence_claims','manual'),
        );
        foreach($sources as $key=>$v){
            $exists=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE source_key=%s",$key));
            $data=array('label'=>$v[0],'source_kind'=>$v[1],'authority_profile'=>$v[2],'existing_store'=>$v[3],'freshness_policy'=>$v[4],'active'=>1,'updated_at'=>$now);
            if($exists)$wpdb->update($table,$data,array('id'=>$exists));else{$data['source_key']=$key;$data['created_at']=$now;$wpdb->insert($table,$data);}
        }
    }

    private function source_key_for_event($row) {
        $src=strtolower(trim((string)($row['source']??'')));
        if($src==='destination_one_open_data')return 'DESTINATION_ONE';
        if(in_array($src,array('open_data_finder','et4'),true))return 'ET4_RUHR_MASTER';
        return 'ET4_RUHR_MASTER';
    }

    private function normalize_text($value) {
        if(is_array($value)||is_object($value))$value=wp_json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $value=html_entity_decode(wp_strip_all_tags((string)$value),ENT_QUOTES|ENT_HTML5,'UTF-8');
        return strtolower(preg_replace('/\s+/u',' ',trim($value)));
    }

    public function classify_row($row) {
        $city=(string)($row['city']??'');
        $inside=class_exists('RuhrpottGuru_Places_Backend') && RuhrpottGuru_Places_Backend::instance()->intelligence_is_metropole_ruhr_city($city);
        $title=$this->normalize_text($row['title']??'');
        $venue=$this->normalize_text($row['venue_name']??'');
        $organizer=$this->normalize_text($row['organizer_json']??'');
        $info=$this->normalize_text($row['event_info_json']??'');
        $raw=$this->normalize_text($row['raw_payload']??'');
        $body=trim($info.' '.$raw);
        $all=trim($title.' '.$venue.' '.$organizer.' '.$body);

        $negative=(bool)preg_match('/\b(?:iga\s*61|iga\*61|iga\s*1961|internationale\s+gartenausstellung\s+1961)\b/u',$all);
        $organizerIga=(strpos($organizer,'iga 2027 ruhrgebiet')!==false || strpos($organizer,'internationale gartenausstellung 2027')!==false);
        $titleIga=(bool)preg_match('/\biga\s*2027\b/u',$title);
        $bodyIga=(bool)preg_match('/\biga\s*2027\b/u',$body);
        $knownVenue=(bool)preg_match('/\b(?:kokerei hansa|deusenberg|rheinpark|nordsternpark|emscherland|victoria i|victoria ii|zukunftsgarten)\b/u',$venue.' '.$body);
        $categoryIga=(bool)preg_match('/(?:"categories"|"keywords"|"features")[^\]]*\biga\s*2027\b/u',$body);

        $evidence=array(
            'inside_metropole_ruhr'=>array('matched'=>$inside,'weight'=>$inside?0:-1000,'value'=>$city),
            'negative_historic_iga'=>array('matched'=>$negative,'weight'=>$negative?-1000:0),
            'organizer_iga'=>array('matched'=>$organizerIga,'weight'=>$organizerIga?95:0),
            'title_iga_2027'=>array('matched'=>$titleIga,'weight'=>$titleIga?80:0),
            'category_iga_2027'=>array('matched'=>$categoryIga,'weight'=>$categoryIga?90:0),
            'body_iga_2027'=>array('matched'=>$bodyIga,'weight'=>$bodyIga?30:0),
            'known_iga_venue'=>array('matched'=>$knownVenue,'weight'=>$knownVenue?35:0),
        );
        $score=0;foreach($evidence as $e)$score+=(int)$e['weight'];
        if(!$inside || $negative){$value='rejected';$confidence=1.0;}
        elseif($organizerIga || $categoryIga || $titleIga){$value='confirmed';$confidence=0.98;}
        elseif($bodyIga && $knownVenue){$value='probable';$confidence=0.85;}
        elseif($bodyIga || $knownVenue){$value='review';$confidence=0.60;}
        else{$value='rejected';$confidence=0.95;}
        return array('value'=>$value,'confidence'=>$confidence,'score'=>$score,'evidence'=>$evidence,'classifier_version'=>self::CLASSIFIER_VERSION);
    }

    private function upsert_claim($event,$result) {
        global $wpdb;$claims=$this->table('intelligence_claims');$now=current_time('mysql');
        $source=$this->source_key_for_event($event);$sourceRecord=(string)($event['external_id']??$event['id']);
        $where=array('entity_type'=>'event','entity_id'=>(int)$event['id'],'namespace'=>'iga','attribute'=>'membership','source_key'=>$source,'source_record_id'=>$sourceRecord);
        $data=array('value_text'=>$result['value'],'value_json'=>wp_json_encode(array('score'=>$result['score']),JSON_UNESCAPED_UNICODE),'claim_status'=>'active','confidence'=>$result['confidence'],'evidence_json'=>wp_json_encode($result['evidence'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'parser_version'=>self::CLASSIFIER_VERSION,'resolver_version'=>self::RESOLVER_VERSION,'source_observed_at'=>(string)($event['source_changed_at']??$event['last_seen_at']??$now),'updated_at'=>$now);
        $id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $claims WHERE entity_type=%s AND entity_id=%d AND namespace=%s AND attribute=%s AND source_key=%s AND source_record_id=%s",$where['entity_type'],$where['entity_id'],$where['namespace'],$where['attribute'],$where['source_key'],$where['source_record_id']));
        $changed=true;
        if($id){$old=$wpdb->get_row($wpdb->prepare("SELECT value_text,confidence,evidence_json,parser_version FROM $claims WHERE id=%d",$id),ARRAY_A);$changed=!$old||$old['value_text']!==$data['value_text']||(string)$old['confidence']!==(string)$data['confidence']||$old['evidence_json']!==$data['evidence_json']||$old['parser_version']!==$data['parser_version'];if($changed)$wpdb->update($claims,$data,array('id'=>$id));}
        else{$data=array_merge($where,$data,array('created_at'=>$now));$wpdb->insert($claims,$data);$id=(int)$wpdb->insert_id;}
        if($changed)$this->resolve_event((int)$event['id']);
        return $changed;
    }

    private function resolve_event($eventId) {
        global $wpdb;$claims=$this->table('intelligence_claims');$snap=$this->table('resolved_snapshots');$now=current_time('mysql');
        $rows=(array)$wpdb->get_results($wpdb->prepare("SELECT * FROM $claims WHERE entity_type='event' AND entity_id=%d AND namespace='iga' AND attribute='membership' AND claim_status='active' ORDER BY updated_at DESC",$eventId),ARRAY_A);
        if(!$rows)return;
        $winner=null;foreach($rows as $r){if($r['source_key']==='RPG_EDITORIAL'){$winner=$r;break;}}if(!$winner)$winner=$rows[0];
        $inputHash=hash('sha256',wp_json_encode(array_map(function($r){return array($r['id'],$r['value_text'],$r['confidence'],$r['updated_at']);},$rows)));
        $resolved=array('membership'=>$winner['value_text'],'confidence'=>(float)$winner['confidence'],'source_key'=>$winner['source_key'],'claim_id'=>(int)$winner['id'],'resolver_version'=>self::RESOLVER_VERSION);
        $data=array('resolved_json'=>wp_json_encode($resolved,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'resolver_version'=>self::RESOLVER_VERSION,'input_hash'=>$inputHash,'resolved_at'=>$now);
        $id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $snap WHERE entity_type='event' AND entity_id=%d AND namespace='iga'",$eventId));
        if($id)$wpdb->update($snap,$data,array('id'=>$id));else{$data=array_merge(array('entity_type'=>'event','entity_id'=>$eventId,'namespace'=>'iga'),$data);$wpdb->insert($snap,$data);}
    }

    public function start_reprocess() {
        $state=array('status'=>'running','cursor'=>0,'processed'=>0,'changed'=>0,'confirmed'=>0,'probable'=>0,'review'=>0,'rejected'=>0,'errors'=>0,'started_at'=>current_time('mysql'),'last_batch_at'=>'','finished_at'=>'','classifier_version'=>self::CLASSIFIER_VERSION);
        update_option(self::STATE_OPTION,$state,false);delete_option(self::LOCK_OPTION);wp_clear_scheduled_hook(self::WORKER_HOOK);wp_schedule_single_event(time()+1,self::WORKER_HOOK);return $state;
    }

    public function run_batch() {
        global $wpdb;$state=get_option(self::STATE_OPTION,array());if(!is_array($state)||($state['status']??'')!=='running')return;
        $lock=(int)get_option(self::LOCK_OPTION,0);if($lock && $lock>time()-300){if(!wp_next_scheduled(self::WORKER_HOOK))wp_schedule_single_event(time()+15,self::WORKER_HOOK);return;}if($lock)delete_option(self::LOCK_OPTION);if(!add_option(self::LOCK_OPTION,time(),'','no'))return;
        $events=$this->table('events');$cursor=(int)($state['cursor']??0);
        $rows=(array)$wpdb->get_results($wpdb->prepare("SELECT id,external_id,source,title,organizer_json,event_info_json,venue_name,city,raw_payload,source_changed_at,last_seen_at FROM $events WHERE id>%d ORDER BY id ASC LIMIT %d",$cursor,self::BATCH_SIZE),ARRAY_A);
        if(!$rows){$state['status']='finished';$state['finished_at']=current_time('mysql');$state['last_batch_at']=$state['finished_at'];update_option(self::STATE_OPTION,$state,false);delete_option(self::LOCK_OPTION);return;}
        foreach($rows as $row){try{$result=$this->classify_row($row);$changed=$this->upsert_claim($row,$result);$state['processed']++;if($changed)$state['changed']++;$v=$result['value'];if(isset($state[$v]))$state[$v]++;$state['cursor']=(int)$row['id'];}catch(Throwable $e){$state['errors']++;$state['last_error']=$e->getMessage();$state['cursor']=(int)$row['id'];}}
        $state['last_batch_at']=current_time('mysql');update_option(self::STATE_OPTION,$state,false);delete_option(self::LOCK_OPTION);wp_schedule_single_event(time()+2,self::WORKER_HOOK);
    }

    public function admin_start() {
        if(!current_user_can('manage_options'))wp_die('Keine Berechtigung.');check_admin_referer('rg_iga_intelligence_start');$this->start_reprocess();
        wp_safe_redirect(add_query_arg(array('page'=>'rp-events','view'=>'iga','rg_iga_notice'=>rawurlencode('IGA-Bestandprüfung gestartet. Der lokale Worker verarbeitet die gespeicherten Event-Rohdaten im Hintergrund.')),admin_url('admin.php')));exit;
    }

    public function admin_override() {
        if(!current_user_can('manage_options'))wp_die('Keine Berechtigung.');check_admin_referer('rg_iga_intelligence_override');
        global $wpdb;$eventId=absint($_POST['event_id']??0);$decision=sanitize_key((string)($_POST['decision']??''));if(!$eventId||!in_array($decision,array('confirmed','rejected'),true))wp_die('Ungültige Entscheidung.');
        $claims=$this->table('intelligence_claims');$now=current_time('mysql');$sourceRecord='event:'.$eventId;$id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $claims WHERE entity_type='event' AND entity_id=%d AND namespace='iga' AND attribute='membership' AND source_key='RPG_EDITORIAL' AND source_record_id=%s",$eventId,$sourceRecord));
        $data=array('value_text'=>$decision,'claim_status'=>'active','confidence'=>1.0,'evidence_json'=>wp_json_encode(array('editorial'=>array('matched'=>true,'user_id'=>get_current_user_id()))),'parser_version'=>'editorial-1.0','resolver_version'=>self::RESOLVER_VERSION,'source_observed_at'=>$now,'updated_at'=>$now);
        if($id)$wpdb->update($claims,$data,array('id'=>$id));else{$data=array_merge(array('entity_type'=>'event','entity_id'=>$eventId,'namespace'=>'iga','attribute'=>'membership','source_key'=>'RPG_EDITORIAL','source_record_id'=>$sourceRecord,'created_at'=>$now),$data);$wpdb->insert($claims,$data);}
        $this->resolve_event($eventId);wp_safe_redirect(add_query_arg(array('page'=>'rp-events','view'=>'iga','rg_iga_notice'=>rawurlencode('Redaktionelle IGA-Entscheidung gespeichert.')),admin_url('admin.php')));exit;
    }

    public function render_admin_page() {
        if(!current_user_can('manage_options'))wp_die('Keine Berechtigung.');global $wpdb;$claims=$this->table('intelligence_claims');$events=$this->table('events');$snap=$this->table('resolved_snapshots');$state=get_option(self::STATE_OPTION,array());if(!is_array($state))$state=array();
        $counts=array('confirmed'=>0,'probable'=>0,'review'=>0,'rejected'=>0);$tableExists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$claims))===$claims;
        if($tableExists){foreach(array_keys($counts) as $v){$needle='"membership":"'.$v.'"';$counts[$v]=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $snap WHERE entity_type='event' AND namespace='iga' AND resolved_json LIKE %s",'%'.$wpdb->esc_like($needle).'%'));}}
        $rows=$tableExists?(array)$wpdb->get_results("SELECT c.*,e.title,e.city,e.start_at FROM $claims c JOIN $events e ON e.id=c.entity_id WHERE c.entity_type='event' AND c.namespace='iga' AND c.attribute='membership' AND c.source_key<>'RPG_EDITORIAL' AND c.value_text IN ('confirmed','probable','review') ORDER BY FIELD(c.value_text,'review','probable','confirmed'),c.confidence DESC,e.start_at ASC LIMIT 200",ARRAY_A):array();
        echo '<div class="wrap"><h1>RP Redaktion · Events</h1><nav class="nav-tab-wrapper" style="margin-bottom:16px"><a class="nav-tab" href="'.esc_url(add_query_arg(array('page'=>'rp-events'),admin_url('admin.php'))).'">Events</a><a class="nav-tab nav-tab-active" href="'.esc_url(add_query_arg(array('page'=>'rp-events','view'=>'iga'),admin_url('admin.php'))).'">IGA 2027</a></nav>';
        if(!empty($_GET['rg_iga_notice']))echo '<div class="notice notice-info is-dismissible"><p>'.esc_html(rawurldecode(wp_unslash($_GET['rg_iga_notice']))).'</p></div>';
        echo '<h2>IGA 2027 · Dark Launch</h2><p>Interne Klassifikation auf Basis der bereits gespeicherten ET4/destination.one-Rohdaten. Noch keine öffentliche REST-Ausgabe, kein Badge und kein Ranking-Eingriff.</p>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;max-width:900px;margin:16px 0">';foreach(array('confirmed'=>'Bestätigt','probable'=>'Wahrscheinlich','review'=>'Prüfen','rejected'=>'Ohne IGA / abgelehnt') as $k=>$label)echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px"><strong style="font-size:24px">'.number_format_i18n($counts[$k]).'</strong><br><small>'.esc_html($label).'</small></div>';echo '</div>';
        echo '<p><strong>Classifier:</strong> '.esc_html(self::CLASSIFIER_VERSION).' · <strong>Worker:</strong> '.esc_html((string)($state['status']??'noch nicht gestartet')).' · verarbeitet '.number_format_i18n((int)($state['processed']??0)).' · geändert '.number_format_i18n((int)($state['changed']??0)).' · Fehler '.number_format_i18n((int)($state['errors']??0)).(!empty($state['last_batch_at'])?' · zuletzt '.esc_html($state['last_batch_at']):'').(!empty($state['last_error'])?' · <strong style="color:#b32d2e">letzter Fehler:</strong> '.esc_html((string)$state['last_error']):'').'</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="margin:12px 0 20px"><input type="hidden" name="action" value="rg_iga_intelligence_start">';wp_nonce_field('rg_iga_intelligence_start');submit_button('IGA-Bestand prüfen','primary','submit',false);echo '</form>';
        echo '<h2>Treffer zur redaktionellen Prüfung</h2><div style="overflow:auto;max-width:1200px"><table class="widefat striped"><thead><tr><th>Status</th><th>Event</th><th>Ort / Termin</th><th>Score / Evidence</th><th>Quelle</th><th>Redaktion</th></tr></thead><tbody>';
        foreach($rows as $r){$ev=json_decode((string)$r['evidence_json'],true);$score=json_decode((string)$r['value_json'],true);$hits=array();foreach((array)$ev as $key=>$x)if(!empty($x['matched']))$hits[]=$key;echo '<tr><td><strong>'.esc_html(strtoupper($r['value_text'])).'</strong><br><small>'.esc_html((string)$r['confidence']).'</small></td><td><strong>'.esc_html($r['title']).'</strong><br>#'.intval($r['entity_id']).'</td><td>'.esc_html($r['city']).'<br><small>'.esc_html($r['start_at']).'</small></td><td>'.intval($score['score']??0).'<br><small>'.esc_html(implode(', ',$hits)).'</small></td><td>'.esc_html($r['source_key']).'<br><code>'.esc_html($r['source_record_id']).'</code></td><td><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:flex;gap:5px;flex-wrap:wrap"><input type="hidden" name="action" value="rg_iga_intelligence_override"><input type="hidden" name="event_id" value="'.intval($r['entity_id']).'">';wp_nonce_field('rg_iga_intelligence_override');echo '<button class="button button-small" name="decision" value="confirmed">Bestätigen</button><button class="button button-small" name="decision" value="rejected">Ablehnen</button></form></td></tr>';}
        if(!$rows)echo '<tr><td colspan="6">Noch keine IGA-Treffer. Zuerst „IGA-Bestand prüfen“ starten.</td></tr>';echo '</tbody></table></div></div>';
    }
}
