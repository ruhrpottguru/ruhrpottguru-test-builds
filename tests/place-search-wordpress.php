<?php
// Isolated CI database only. Never point RPG_TEST_WP_ROOT at a production install.
$root = getenv('RPG_TEST_WP_ROOT');
if (!$root || !file_exists($root.'/wp-load.php')) throw new RuntimeException('Disposable WP root required');
require $root.'/wp-load.php';
if (DB_NAME !== 'rpg_search_test') throw new RuntimeException('Refusing non-test database');
add_filter('pre_http_request', function () { return new WP_Error('fixture_network_disabled','No external data during fixture tests'); }, PHP_INT_MAX);
// Load the exact complete class, without bootstrapping import workers or add-on singletons.
$source = file_get_contents(__DIR__.'/../wordpress/plugins/ruhrpott-guru-places/ruhrpott-guru-places.php');
$start = strpos($source, 'final class RuhrpottGuru_Places_Backend {');
$end = strpos($source, "require_once __DIR__ . '/includes/intelligence/class-rpg-iga-intelligence.php';", $start);
if ($start === false || $end === false) throw new RuntimeException('Class boundary not found');
eval(substr($source,$start,$end-$start));
$reflection = new ReflectionClass('RuhrpottGuru_Places_Backend');
$backend = $reflection->newInstanceWithoutConstructor();
$backend->install_schema();
global $wpdb;
$wpdb->show_errors();
$now = current_time('mysql');
function fixture($id,$name,$city,$tags,$extra = array()) {
    global $wpdb,$now;
    $row = array_merge(array('id'=>$id,'canonical_key'=>sha1('fixture-'.$id),'name'=>$name,'normalized_name'=>strtolower($name),'lat'=>51.4861676,'lng'=>6.8870987,'municipality_slug'=>strtolower($city),'city'=>$city,'primary_category'=>'activities','primary_subcategory'=>'Freizeit & Action','raw_tags'=>wp_json_encode($tags),'first_seen_at'=>$now,'last_seen_at'=>$now,'last_synced_at'=>$now,'updated_at'=>$now,'quality_score'=>.49),$extra);
    if ($wpdb->insert($wpdb->prefix.'rg_places',$row) === false) throw new RuntimeException($wpdb->last_error);
}
$common = array('name'=>'Topgolf','sport'=>'golf','website'=>'https://topgolfoberhausen.com/','opening_hours'=>'Mo-Su 10:00-00:00');
fixture(8577,'Topgolf','Oberhausen',array_merge($common,array('building'=>'sports_centre','golf'=>'clubhouse')),array('quality_score'=>.8,'street'=>'Brammenring','house_number'=>'30','postcode'=>'46047','primary_osm_type'=>'w','primary_osm_id'=>1051311896));
fixture(8274,'Topgolf','Oberhausen',array_merge($common,array('leisure'=>'sports_centre')),array('primary_osm_id'=>10225168937));
fixture(8576,'Topgolf','Oberhausen',array_merge($common,array('building'=>'sports_centre','golf'=>'driving_range')),array('primary_osm_type'=>'w','primary_osm_id'=>1051311895));
fixture(9001,'Topgolf','Essen',array_merge($common,array('website'=>'https://topgolf-essen.example.invalid/')));
fixture(9002,'Topgolf','Oberhausen',$common,array('active'=>0));
fixture(9003,'Topgolf','Oberhausen',$common,array('lifecycle_status'=>'closed'));
fixture(9004,'100% Spaß','Essen',array('leisure'=>'miniature_golf'));
fixture(9005,'1000 Spaß','Essen',array('leisure'=>'miniature_golf'));
fixture(9006,'A_B','Essen',array('leisure'=>'miniature_golf'));
fixture(9007,'AXB','Essen',array('leisure'=>'miniature_golf'));
fixture(9008,"O'Brien",'Essen',array('leisure'=>'miniature_golf'));
fixture(9009,'Rollenfläche','Essen',array('leisure'=>'skate_park'));
$backend->register_rest_routes();
$count = 0;
function check_search($query,$expected,$method = 'GET') {
    global $wpdb,$count;
    $wpdb->last_error = '';
    $request = new WP_REST_Request($method,'/ruhrpottguru/v1/global-search');
    if ($method === 'POST') { $request->set_header('Content-Type','application/json'); $request->set_body(wp_json_encode(array('q'=>$query,'limit'=>30))); }
    else $request->set_query_params(array('q'=>$query,'limit'=>30));
    $response = rest_do_request($request);
    if ($response->get_status() !== 200 || $wpdb->last_error) throw new RuntimeException('REST/SQL error: '.$wpdb->last_error);
    $data = $response->get_data();
    $actual = array_column($data['groups']['activities'] ?? array(),'id');
    $sorted = $actual; sort($sorted); sort($expected);
    if ($sorted !== $expected) throw new RuntimeException($method.' '.$query.': '.wp_json_encode($actual));
    $count++;
    return $data;
}
$oh = array('place:8577','place:8274','place:8576');
foreach (array('GET','POST') as $method) {
    check_search('Topgolf',array_merge($oh,array('place:9001')),$method);
    $data = check_search('Topgolf Oberhausen',$oh,$method);
    if ($data['groups']['activities'][0]['id'] !== 'place:8577') throw new RuntimeException('Quality ordering changed');
    if (count($data['groups']['activities']) !== 3) throw new RuntimeException('Unpictured places hidden');
    check_search('Top Golf Oberhausen',$oh,$method);
    check_search('Oberhausen Topgolf',$oh,$method);
    check_search(" Topgolf\t Oberhausen ",$oh,$method);
    check_search('Topgolf Essen',array('place:9001'),$method);
    check_search('Topgolf Dortmund',array(),$method);
    check_search('Topgolf Oberhausen Tennis',array(),$method);
    check_search('100%',array('place:9004'),$method);
    check_search('A_B',array('place:9006'),$method);
    check_search("O'Brien Essen",array('place:9008'),$method);
    check_search("' OR 1=1 --",array(),$method);
    check_search('Skatepark',array('place:9009'),$method);
}
// Reproduce the SEO/search mismatch without changing source admission rules.
$row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}rg_places WHERE id=8577",ARRAY_A);
$projection = new ReflectionMethod($backend,'activity_v2_public_projection');
$seo = new ReflectionMethod($backend,'seo_public_place_row_v101096');
if ($projection->invoke($backend,$row) !== null || $seo->invoke($backend,8577) !== null) throw new RuntimeException('Expected unverified golf rejection');
$tags = json_decode($row['raw_tags'],true); $tags['access'] = 'customers';
$row['raw_tags'] = wp_json_encode($tags);
if ($projection->invoke($backend,$row) === null) throw new RuntimeException('Verified customer access not accepted by existing rules');
// This changed row is only an in-memory counterexample, not a data-repair test.
echo "$count real WordPress REST / wpdb / MySQL search cases passed.\n";
echo "Existing golf admission rejects absent access and accepts in-memory customer-access evidence. No production change.\n";
echo 'WordPress '.get_bloginfo('version').'; MySQL '.$wpdb->db_version().'; PHP '.PHP_VERSION."\n";

// Validate the temporary diagnostic tool with no database mutations and no public route.
require __DIR__.'/../tools/rpg-topgolf-diagnostics/rpg-topgolf-diagnostics.php';
$admin = get_user_by('login','fixture'); wp_set_current_user($admin->ID);
$wpdb->insert($wpdb->prefix.'rg_place_overrides',array('place_id'=>8577,'override_data'=>wp_json_encode(array('access'=>'customers','private_note'=>'DO_NOT_EXPORT_PRIVATE_MARKER')),'created_at'=>$now,'updated_at'=>$now));
$read_only = function ($sql) {
    if (!preg_match('/^\s*(SELECT|SHOW)\b/i',$sql)) throw new RuntimeException('Diagnostic attempted database write');
    return $sql;
};
add_filter('query',$read_only,PHP_INT_MAX);
$report = RPG_Topgolf_Diagnostics::report();
remove_filter('query',$read_only,PHP_INT_MAX);
if (count($report['places']) !== 3 || $report['errors']) throw new RuntimeException('Diagnostic did not resolve three target rows');
if ($report['backend']['sha256'] !== hash_file('sha256',__DIR__.'/../wordpress/plugins/ruhrpott-guru-places/ruhrpott-guru-places.php')) throw new RuntimeException('Diagnostic source hash mismatch');
if (strpos(wp_json_encode($report),'DO_NOT_EXPORT_PRIVATE_MARKER') !== false) throw new RuntimeException('Diagnostic leaked private note');
if (has_action('admin_post_nopriv_rpg_topgolf_report')) throw new RuntimeException('Unexpected unauthenticated download hook');
wp_set_current_user(0);
$die_handler = function () { return function () { throw new RuntimeException('EXPECTED_FORBIDDEN'); }; };
add_filter('wp_die_handler',$die_handler);
$denied = false;
try { RPG_Topgolf_Diagnostics::report(); } catch (RuntimeException $e) { $denied = $e->getMessage() === 'EXPECTED_FORBIDDEN'; }
remove_filter('wp_die_handler',$die_handler);
if (!$denied) throw new RuntimeException('Diagnostic permission guard failed');
echo "Diagnostic checks passed: administrator only, SELECT/SHOW only, three IDs, exact code hash, private-note exclusion, no unauthenticated hook.\n";
