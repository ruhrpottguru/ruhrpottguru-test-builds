<?php
// Runs against a disposable WordPress/MariaDB install, never against production.
require getenv('POTTFUCHS_TEST_WP') . '/wp-load.php';
if (!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON || DB_NAME !== 'pottfuchs_test') {
    throw new RuntimeException('Disposable database and disabled cron required');
}
add_filter('pre_http_request', function () { return new WP_Error('test_offline', 'No upstream imports in integration test'); });
require WP_PLUGIN_DIR . '/ruhrpott-guru-places/ruhrpott-guru-places.php';
$backend=RuhrpottGuru_Places_Backend::instance();
$backend->install_schema();
$wpdb->show_errors();
$table=$wpdb->prefix.'rg_events';
$now=current_time('mysql');
$future=wp_date('Y-m-d H:i:s',time()+2*DAY_IN_SECONDS);
$end=wp_date('Y-m-d H:i:s',time()+2*DAY_IN_SECONDS+7200);
$n=0;
function check_scope($value,$label){global $n;$n++;if(!$value)throw new RuntimeException('FAIL: '.$label);}
function add_event($id,$city='Duisburg',$extra=array()){
    global $wpdb,$table,$future,$end,$now;
    $row=array_merge(array('id'=>$id,'canonical_key'=>sha1('fixture-'.$id),'external_id'=>'fixture-'.$id,
        'source'=>'et4','title'=>'Testtermin '.substr(sha1('title-'.$id),0,18),'venue_name'=>'Spielort '.substr(sha1('venue-'.$id),0,18),
        'city'=>$city,'lat'=>null,'lng'=>null,'start_at'=>$future,'end_at'=>$end,'status'=>'active',
        'street'=>'','house_number'=>'','postcode'=>'','raw_payload'=>'{}',
        'first_seen_at'=>$now,'last_seen_at'=>$now,'last_synced_at'=>$now,'updated_at'=>$now),$extra);
    if($wpdb->insert($table,$row)===false)throw new RuntimeException($wpdb->last_error);
}
function get_scope($city='Duisburg',$km=5){global $backend;return $backend->frontend_events_query(51.435,6.763,$km,$city);}
function by_fixture($response,$id){foreach($response['items'] as $i)if(($i['rpg_event_db']['eventId']??0)===$id)return $i;return null;}
function ids_scope($response){return array_column(array_column($response['items'],'rpg_event_db'),'eventId');}
update_option('rg_events_last_ingest',array('timestamp'=>time()));
update_option('rg_events_last_complete_nightly_at',time());
$sources=array('et4','open_data_finder','destination_one_open_data','stadt_selm_calendar','wesel_marketing_calendar','rpg_seasonal_editorial');
for($id=1;$id<=701;$id++)add_event($id,'Duisburg',array('source'=>$sources[$id%count($sources)]));
add_event(710,'Duisburg',array('lat'=>51.435,'lng'=>6.763,'street'=>'Teststraße','house_number'=>'12','postcode'=>'47051'));
add_event(711,'Duisburg',array('lat'=>51.498,'lng'=>6.763)); // about 7 km
add_event(712,'Duisburg',array('lat'=>51.705,'lng'=>6.763)); // about 30 km
add_event(713,'Duisburg',array('lat'=>null,'lng'=>6.763,'street'=>'Teststraße','house_number'=>'14','postcode'=>'47051'));
add_event(714,'Duisburg',array('status'=>'cancelled'));
add_event(715,'Duisburg',array('status'=>'inactive'));
add_event(716,'Duisburg',array('start_at'=>'2020-01-01 10:00:00','end_at'=>'2020-01-01 12:00:00'));
add_event(717,'Essen');
add_event(718,'');
$r=get_scope();
check_scope($r['ok']&&$r['ready'],'full production method returns ready');
check_scope(count($r['items'])===703,'701 city matches plus precise local and partial coordinate, no 500 cap');
foreach(range(1,701) as $id){
    $item=by_fixture($r,$id);
    check_scope($item!==null,'all paginated candidates survive clustering '.$id);
    check_scope($item['rpg_event_db']['distanceKm']===null&&$item['rpg_event_db']['cityScope']===true,'city scope has no distance '.$id);
    check_scope(!isset($item['geo'])&&!isset($item['latitude'])&&!isset($item['longitude']),'no fabricated coordinates '.$id);
    check_scope(($item['rpg_geo_label']??'')==='Duisburg','actual city label '.$id);
}
$partial=by_fixture($r,713);
check_scope($partial['address']['street']==='Teststraße'&&$partial['address']['houseNumber']==='14','known address preserved without coordinates');
check_scope($partial['rpg_event_db']['distanceKm']===null&&!isset($partial['geo']),'partial coordinate never becomes route point');
$precise=by_fixture($r,710);
check_scope($precise['rpg_event_db']['cityScope']===false&&$precise['rpg_event_db']['distanceKm']===0.0,'precise geometry retains genuine distance');
check_scope($precise['geo']['latitude']===51.435,'precise target preserved');
foreach(array(711,712,714,715,716,717,718) as $id)check_scope(by_fixture($r,$id)===null,'excluded outside/expired/inactive/other/unknown city '.$id);
check_scope(by_fixture(get_scope('Duisburg',10),711)!==null,'exact 10 km includes 7 km event');
check_scope(by_fixture(get_scope('Duisburg',10),712)===null,'exact 10 km excludes 30 km event');
check_scope(by_fixture(get_scope('Duisburg',50),712)!==null,'exact 50 km remains available');
check_scope(count(get_scope('')['items'])===1,'no explicit city means no coordinate-less city candidates');
check_scope(count(get_scope('   ')['items'])===1,'blank city never selects unknown locations');
check_scope(by_fixture(get_scope('  Duisburg  '),701)!==null,'explicit city trimmed');
check_scope(count(get_scope("Duisburg' OR 1=1 --")['items'])===1,'prepared city cannot expand query');
$seed=(new ReflectionMethod($backend,'municipalities_seed'))->invoke($backend);
check_scope(count($seed)===53,'canonical 53 municipality seed unchanged');
foreach($seed as $k=>$municipality){$name=$municipality[0];add_event(1000+$k,$name);}
foreach($seed as $k=>$municipality){
    $city=$municipality[0];$rr=get_scope($city);
    $item=by_fixture($rr,1000+$k);
    check_scope($item!==null&&($item['rpg_geo_label']??'')===$city,'all 53 municipalities '.$city);
    check_scope($item['rpg_event_db']['distanceKm']===null,'no fabricated municipal distance '.$city);
}
check_scope($wpdb->last_error==='','no database error');
// Fixture output drives unmodified web and Android JavaScript contract checks.
$out=dirname(__DIR__).'/cityscope-release';if(!is_dir($out))mkdir($out,0777,true);
file_put_contents($out.'/integration-payload.json',wp_json_encode(array('city'=>by_fixture($r,1),'partial'=>$partial,'precise'=>$precise),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo "$n real WordPress/MariaDB city-scope assertions passed\n";
