<?php
if (!defined('ABSPATH')) exit;

/**
 * RuhrpottGuru Outdoor Coverage Audit – v1.10.139.
 *
 * Admin-only, non-public coverage measurement for Hunde & Wald/Natur.
 * One atomic Overpass request is executed per tick. Progress is persisted after
 * every successful step, so a slow or unavailable provider cannot throw away
 * an already completed municipality step. WP-Cron continues the run server-side.
 * It never inserts/updates/deletes Places, Media, Events or Outdoor entities.
 */
final class RuhrpottGuru_Outdoor_Coverage_Audit {
    const BUILD_VERSION = '1.10.139';
    const RUN_OPTION = 'rg_outdoor_coverage_run_v1';
    const LOCK_TRANSIENT = 'rg_outdoor_coverage_next_lock_v2';
    const CRON_HOOK = 'rg_outdoor_coverage_tick_v2';
    const QUERY_VERSION = 'outdoor-coverage-1.5';
    const STEP_DELAY = 30;
    const MAX_STEP_ATTEMPTS = 4;

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action(self::CRON_HOOK, array($this, 'cron_tick'), 10, 1);
        add_action('wp_loaded', array($this, 'ensure_scheduled'), 20);
    }

    public function can_manage() {
        return current_user_can('manage_options');
    }

    public function register_rest_routes() {
        register_rest_route('ruhrpottguru/v1', '/outdoor/coverage/status', array(
            'methods' => 'GET', 'callback' => array($this, 'rest_status'), 'permission_callback' => array($this, 'can_manage'),
        ));
        register_rest_route('ruhrpottguru/v1', '/outdoor/coverage/start', array(
            'methods' => 'POST', 'callback' => array($this, 'rest_start'), 'permission_callback' => array($this, 'can_manage'),
        ));
        register_rest_route('ruhrpottguru/v1', '/outdoor/coverage/next', array(
            'methods' => 'POST', 'callback' => array($this, 'rest_next'), 'permission_callback' => array($this, 'can_manage'),
        ));
    }

    private function municipalities() {
        return array(
            array('city'=>'Bochum','ags'=>'05911000','admin_level'=>6),
            array('city'=>'Bottrop','ags'=>'05512000','admin_level'=>6),
            array('city'=>'Dortmund','ags'=>'05913000','admin_level'=>6),
            array('city'=>'Duisburg','ags'=>'05112000','admin_level'=>6),
            array('city'=>'Essen','ags'=>'05113000','admin_level'=>6),
            array('city'=>'Gelsenkirchen','ags'=>'05513000','admin_level'=>6),
            array('city'=>'Hagen','ags'=>'05914000','admin_level'=>6),
            array('city'=>'Hamm','ags'=>'05915000','admin_level'=>6),
            array('city'=>'Herne','ags'=>'05916000','admin_level'=>6),
            array('city'=>'Mülheim an der Ruhr','ags'=>'05117000','admin_level'=>6),
            array('city'=>'Oberhausen','ags'=>'05119000','admin_level'=>6),
            array('city'=>'Breckerfeld','ags'=>'05954004','admin_level'=>8),
            array('city'=>'Ennepetal','ags'=>'05954008','admin_level'=>8),
            array('city'=>'Gevelsberg','ags'=>'05954012','admin_level'=>8),
            array('city'=>'Hattingen','ags'=>'05954016','admin_level'=>8),
            array('city'=>'Herdecke','ags'=>'05954020','admin_level'=>8),
            array('city'=>'Schwelm','ags'=>'05954024','admin_level'=>8),
            array('city'=>'Sprockhövel','ags'=>'05954028','admin_level'=>8),
            array('city'=>'Wetter (Ruhr)','ags'=>'05954032','admin_level'=>8),
            array('city'=>'Witten','ags'=>'05954036','admin_level'=>8),
            array('city'=>'Castrop-Rauxel','ags'=>'05562004','admin_level'=>8),
            array('city'=>'Datteln','ags'=>'05562008','admin_level'=>8),
            array('city'=>'Dorsten','ags'=>'05562012','admin_level'=>8),
            array('city'=>'Gladbeck','ags'=>'05562014','admin_level'=>8),
            array('city'=>'Haltern am See','ags'=>'05562016','admin_level'=>8),
            array('city'=>'Herten','ags'=>'05562020','admin_level'=>8),
            array('city'=>'Marl','ags'=>'05562024','admin_level'=>8),
            array('city'=>'Oer-Erkenschwick','ags'=>'05562028','admin_level'=>8),
            array('city'=>'Recklinghausen','ags'=>'05562032','admin_level'=>8),
            array('city'=>'Waltrop','ags'=>'05562036','admin_level'=>8),
            array('city'=>'Bergkamen','ags'=>'05978004','admin_level'=>8),
            array('city'=>'Bönen','ags'=>'05978008','admin_level'=>8),
            array('city'=>'Fröndenberg/Ruhr','ags'=>'05978012','admin_level'=>8),
            array('city'=>'Holzwickede','ags'=>'05978016','admin_level'=>8),
            array('city'=>'Kamen','ags'=>'05978020','admin_level'=>8),
            array('city'=>'Lünen','ags'=>'05978024','admin_level'=>8),
            array('city'=>'Schwerte','ags'=>'05978028','admin_level'=>8),
            array('city'=>'Selm','ags'=>'05978032','admin_level'=>8),
            array('city'=>'Unna','ags'=>'05978036','admin_level'=>8),
            array('city'=>'Werne','ags'=>'05978040','admin_level'=>8),
            array('city'=>'Alpen','ags'=>'05170004','admin_level'=>8),
            array('city'=>'Dinslaken','ags'=>'05170008','admin_level'=>8),
            array('city'=>'Hamminkeln','ags'=>'05170012','admin_level'=>8),
            array('city'=>'Hünxe','ags'=>'05170016','admin_level'=>8),
            array('city'=>'Kamp-Lintfort','ags'=>'05170020','admin_level'=>8),
            array('city'=>'Moers','ags'=>'05170024','admin_level'=>8),
            array('city'=>'Neukirchen-Vluyn','ags'=>'05170028','admin_level'=>8),
            array('city'=>'Rheinberg','ags'=>'05170032','admin_level'=>8),
            array('city'=>'Schermbeck','ags'=>'05170036','admin_level'=>8),
            array('city'=>'Sonsbeck','ags'=>'05170040','admin_level'=>8),
            array('city'=>'Voerde (Niederrhein)','ags'=>'05170044','admin_level'=>8),
            array('city'=>'Wesel','ags'=>'05170048','admin_level'=>8),
            array('city'=>'Xanten','ags'=>'05170052','admin_level'=>8),
        );
    }

    private function now_iso() { return gmdate('c'); }

    private function initial_state() {
        $cities = $this->municipalities();
        return array(
            'ok'=>true, 'runId'=>wp_generate_uuid4(), 'status'=>'running',
            'queryVersion'=>self::QUERY_VERSION, 'buildVersion'=>self::BUILD_VERSION,
            'total'=>count($cities), 'processed'=>0, 'success'=>0, 'errors'=>0,
            'currentIndex'=>0, 'startedAt'=>$this->now_iso(), 'updatedAt'=>$this->now_iso(),
            'completedAt'=>null, 'activeCity'=>null, 'nextRunAt'=>null,
            'stepRetries'=>0, 'results'=>array(),
        );
    }

    public function rest_start(WP_REST_Request $request) {
        $existing = get_option(self::RUN_OPTION, array());
        $force = (bool)$request->get_param('force');
        if (is_array($existing) && ($existing['status'] ?? '') === 'running' && !$force) {
            return new WP_REST_Response(array('ok'=>false,'code'=>'coverage_running','message'=>'Ein Coverage-Lauf ist bereits aktiv.','run'=>$this->public_state($existing)),409);
        }
        delete_transient(self::LOCK_TRANSIENT);
        wp_clear_scheduled_hook(self::CRON_HOOK);
        $state = $this->initial_state();
        $this->persist_and_schedule($state, 1);
        return rest_ensure_response($this->public_state($state));
    }

    public function rest_status() {
        $state = get_option(self::RUN_OPTION, array());
        if (!is_array($state) || empty($state['runId'])) {
            return rest_ensure_response(array('ok'=>true,'status'=>'idle','buildVersion'=>self::BUILD_VERSION,'queryVersion'=>self::QUERY_VERSION,'total'=>53,'processed'=>0,'results'=>array()));
        }
        return rest_ensure_response($this->public_state($state));
    }

    private function public_state($state) {
        $state['locked'] = (bool)get_transient(self::LOCK_TRANSIENT);
        $state['writes'] = array('places'=>false,'place_media'=>false,'events'=>false,'event_media'=>false,'outdoor_entities'=>false,'outdoor_entries'=>false,'coverage_state_only'=>true);
        if (isset($state['activeCity']) && is_array($state['activeCity'])) {
            unset($state['activeCity']['areaId'], $state['activeCity']['preferredProvider']);
        }
        return $state;
    }

    public function rest_next(WP_REST_Request $request) {
        $state = get_option(self::RUN_OPTION, array());
        if (!is_array($state) || empty($state['runId'])) {
            return new WP_REST_Response(array('ok'=>false,'code'=>'coverage_not_started','message'=>'Coverage-Lauf wurde noch nicht gestartet.'),409);
        }
        if (($state['status'] ?? '') !== 'running') return rest_ensure_response($this->public_state($state));
        if (get_transient(self::LOCK_TRANSIENT)) {
            return new WP_REST_Response(array('ok'=>false,'code'=>'coverage_locked','message'=>'Ein Coverage-Schritt läuft bereits.','run'=>$this->public_state($state)),409);
        }
        $result = $this->run_one_step((string)$state['runId'], true);
        if (is_wp_error($result)) {
            return new WP_REST_Response(array('ok'=>false,'code'=>$result->get_error_code(),'message'=>$result->get_error_message()),409);
        }
        return rest_ensure_response($result);
    }

    public function cron_tick($run_id = '') {
        $this->run_one_step((string)$run_id, false);
    }

    public function ensure_scheduled() {
        $state = get_option(self::RUN_OPTION, array());
        if (!is_array($state) || empty($state['runId']) || ($state['status'] ?? '') !== 'running') return;
        if (get_transient(self::LOCK_TRANSIENT)) return;
        $run_id = (string)$state['runId'];
        if (wp_next_scheduled(self::CRON_HOOK, array($run_id))) return;
        $next = max(time() + 1, (int)($state['nextRunAt'] ?? 0));
        wp_schedule_single_event($next, self::CRON_HOOK, array($run_id));
    }

    private function run_one_step($run_id, $manual = false) {
        $state = get_option(self::RUN_OPTION, array());
        if (!is_array($state) || empty($state['runId']) || (string)$state['runId'] !== (string)$run_id) {
            return new WP_Error('coverage_run_mismatch', 'Coverage-Lauf ist nicht mehr aktuell.');
        }
        if (($state['status'] ?? '') !== 'running') return array('ok'=>true,'run'=>$this->public_state($state));
        if (get_transient(self::LOCK_TRANSIENT)) return new WP_Error('coverage_locked', 'Ein Coverage-Schritt läuft bereits.');

        wp_clear_scheduled_hook(self::CRON_HOOK, array((string)$state['runId']));
        set_transient(self::LOCK_TRANSIENT, $state['runId'], 90);
        try {
            $cities = $this->municipalities();
            $idx = (int)($state['currentIndex'] ?? 0);
            if ($idx >= count($cities)) {
                $state = $this->finish_state($state);
                update_option(self::RUN_OPTION, $state, false);
                return array('ok'=>true,'run'=>$this->public_state($state));
            }

            if (!isset($state['activeCity']) || !is_array($state['activeCity']) || ($state['activeCity']['ags'] ?? '') !== $cities[$idx]['ags']) {
                $state['activeCity'] = $this->new_city_progress($cities[$idx]);
            }

            $outcome = $this->process_active_step($state['activeCity']);
            $state['activeCity'] = $outcome['city'];
            $state['updatedAt'] = $this->now_iso();

            if (($outcome['type'] ?? '') === 'retry') {
                $state['stepRetries'] = (int)($state['stepRetries'] ?? 0) + 1;
                $delay = $this->retry_delay((int)($state['activeCity']['stepAttempts'] ?? 1));
                $this->persist_and_schedule($state, $delay);
                return array('ok'=>true,'stepStatus'=>'retry_scheduled','delaySeconds'=>$delay,'run'=>$this->public_state($state));
            }

            if (($outcome['type'] ?? '') === 'city_error') {
                $result = $this->public_city_result($state['activeCity']);
                $state['results'][$result['ags']] = $result;
                $state['processed'] = $idx + 1;
                $state['currentIndex'] = $idx + 1;
                $state['errors'] = (int)($state['errors'] ?? 0) + 1;
                $state['activeCity'] = null;
                if ($state['currentIndex'] >= count($cities)) {
                    $state = $this->finish_state($state);
                    update_option(self::RUN_OPTION, $state, false);
                } else {
                    $this->persist_and_schedule($state, self::STEP_DELAY);
                }
                return array('ok'=>true,'cityResult'=>$result,'run'=>$this->public_state($state));
            }

            if (($outcome['type'] ?? '') === 'city_complete') {
                $result = $this->public_city_result($state['activeCity']);
                $state['results'][$result['ags']] = $result;
                $state['processed'] = $idx + 1;
                $state['currentIndex'] = $idx + 1;
                $state['success'] = (int)($state['success'] ?? 0) + 1;
                $state['activeCity'] = null;
                if ($state['currentIndex'] >= count($cities)) {
                    $state = $this->finish_state($state);
                    update_option(self::RUN_OPTION, $state, false);
                } else {
                    $this->persist_and_schedule($state, self::STEP_DELAY);
                }
                return array('ok'=>true,'cityResult'=>$result,'run'=>$this->public_state($state));
            }

            $this->persist_and_schedule($state, self::STEP_DELAY);
            return array('ok'=>true,'stepStatus'=>'step_complete','completedStep'=>$outcome['completedStep'] ?? null,'run'=>$this->public_state($state));
        } finally {
            delete_transient(self::LOCK_TRANSIENT);
        }
    }

    private function new_city_progress($city) {
        return array(
            'city'=>$city['city'],'ags'=>$city['ags'],'adminLevel'=>(int)$city['admin_level'],
            'status'=>'running','step'=>'boundary','stepAttempts'=>0,'relationId'=>null,'areaId'=>null,'preferredProvider'=>'',
            'boundaryRelationCount'=>null,'areaCount'=>null,'dogAreas'=>null,'dogRuleEvidence'=>null,'natureCandidates'=>null,'trailheads'=>null,
            'queryVersion'=>self::QUERY_VERSION,'measuredAt'=>$this->now_iso(),'durationMs'=>0,'sources'=>array(),'error'=>null,'lastTransientError'=>null,
        );
    }

    private function process_active_step($city) {
        $step = (string)($city['step'] ?? 'boundary');
        $started = microtime(true);
        $provider = (string)($city['preferredProvider'] ?? '');

        if ($step === 'boundary') {
            $response = $this->overpass($this->query_boundary_relation($city['ags'], $city['adminLevel']), $provider);
            if (is_wp_error($response)) return $this->transient_step_failure($city, 'boundary_relation', $response, $started);
            $ids = $this->relation_ids($response['elements'] ?? array());
            $city['boundaryRelationCount'] = count($ids);
            $city['sources'][] = $response['meta'];
            if ($city['boundaryRelationCount'] !== 1) return $this->hard_step_failure($city, 'area_resolution_error', $started);
            $city['relationId'] = (int)$ids[0];
            $city['areaId'] = $this->relation_to_area_id($ids[0]);
            $city['preferredProvider'] = (string)($response['meta']['provider'] ?? '');
            return $this->step_success($city, 'area_check', 'boundary', $started);
        }

        if ($step === 'area_check') {
            $response = $this->overpass($this->query_area_check($city['areaId']), $provider);
            if (is_wp_error($response)) return $this->transient_step_failure($city, 'area_check', $response, $started);
            $counts = $this->count_totals($response['elements'] ?? array());
            $city['sources'][] = $response['meta'];
            if (count($counts) !== 1) return $this->hard_step_failure($city, 'area_check_count_contract', $started);
            $city['areaCount'] = $counts[0];
            if ($city['areaCount'] !== 1) return $this->hard_step_failure($city, 'area_resolution_error', $started);
            $city['preferredProvider'] = (string)($response['meta']['provider'] ?? $provider);
            return $this->step_success($city, 'dog_areas', 'area_check', $started);
        }

        if ($step === 'dog_areas') {
            $response = $this->overpass($this->query_dog_areas($city['areaId']), $provider);
            if (is_wp_error($response)) return $this->transient_step_failure($city, 'dog_areas', $response, $started);
            $counts = $this->count_totals($response['elements'] ?? array());
            $city['sources'][] = $response['meta'];
            if (count($counts) !== 1) return $this->hard_step_failure($city, 'dog_areas_count_contract', $started);
            $city['dogAreas'] = $counts[0];
            $city['preferredProvider'] = (string)($response['meta']['provider'] ?? $provider);
            return $this->step_success($city, 'nature', 'dog_areas', $started);
        }

        if ($step === 'nature') {
            $response = $this->overpass($this->query_nature_candidates($city['areaId']), $provider);
            if (is_wp_error($response)) return $this->transient_step_failure($city, 'nature_candidates', $response, $started);
            $counts = $this->count_totals($response['elements'] ?? array());
            $city['sources'][] = $response['meta'];
            if (count($counts) !== 1) return $this->hard_step_failure($city, 'nature_candidates_count_contract', $started);
            $city['natureCandidates'] = $counts[0];
            $city['preferredProvider'] = (string)($response['meta']['provider'] ?? $provider);
            return $this->step_success($city, 'dog_rules', 'nature', $started);
        }

        if ($step === 'dog_rules') {
            $response = $this->overpass($this->query_dog_rule_evidence($city['areaId']), $provider);
            if (is_wp_error($response)) return $this->transient_step_failure($city, 'dog_rule_evidence', $response, $started);
            $counts = $this->count_totals($response['elements'] ?? array());
            $city['sources'][] = $response['meta'];
            if (count($counts) !== 1) return $this->hard_step_failure($city, 'dog_rule_evidence_count_contract', $started);
            $city['dogRuleEvidence'] = $counts[0];
            $city['preferredProvider'] = (string)($response['meta']['provider'] ?? $provider);
            return $this->step_success($city, 'trailheads', 'dog_rules', $started);
        }

        if ($step === 'trailheads') {
            $response = $this->overpass($this->query_trailheads($city['areaId']), $provider);
            if (is_wp_error($response)) return $this->transient_step_failure($city, 'trailheads', $response, $started);
            $counts = $this->count_totals($response['elements'] ?? array());
            $city['sources'][] = $response['meta'];
            if (count($counts) !== 1) return $this->hard_step_failure($city, 'trailheads_count_contract', $started);
            $city['trailheads'] = $counts[0];
            $city['preferredProvider'] = (string)($response['meta']['provider'] ?? $provider);
            $city['status'] = 'ok';
            $city['error'] = null;
            $city['lastTransientError'] = null;
            $city['stepAttempts'] = 0;
            $city['durationMs'] = (int)($city['durationMs'] ?? 0) + (int)round((microtime(true)-$started)*1000);
            return array('type'=>'city_complete','city'=>$city,'completedStep'=>'trailheads');
        }

        return $this->hard_step_failure($city, 'unknown_step', $started);
    }

    private function step_success($city, $next_step, $completed_step, $started) {
        $city['step'] = $next_step;
        $city['stepAttempts'] = 0;
        $city['lastTransientError'] = null;
        $city['durationMs'] = (int)($city['durationMs'] ?? 0) + (int)round((microtime(true)-$started)*1000);
        return array('type'=>'step_complete','city'=>$city,'completedStep'=>$completed_step);
    }

    private function transient_step_failure($city, $label, $error, $started) {
        $city['stepAttempts'] = (int)($city['stepAttempts'] ?? 0) + 1;
        $city['lastTransientError'] = $label.': '.$error->get_error_message();
        $city['durationMs'] = (int)($city['durationMs'] ?? 0) + (int)round((microtime(true)-$started)*1000);
        if ($city['stepAttempts'] < self::MAX_STEP_ATTEMPTS) {
            return array('type'=>'retry','city'=>$city,'completedStep'=>null);
        }
        $city['status'] = 'error';
        $city['error'] = $city['lastTransientError'].' nach '.self::MAX_STEP_ATTEMPTS.' Versuchen';
        return array('type'=>'city_error','city'=>$city,'completedStep'=>null);
    }

    private function hard_step_failure($city, $message, $started) {
        $city['status'] = 'error';
        $city['error'] = $message;
        $city['durationMs'] = (int)($city['durationMs'] ?? 0) + (int)round((microtime(true)-$started)*1000);
        return array('type'=>'city_error','city'=>$city,'completedStep'=>null);
    }

    private function retry_delay($attempt) {
        if ($attempt <= 1) return 60;
        if ($attempt === 2) return 120;
        return 300;
    }

    private function public_city_result($city) {
        unset($city['step'], $city['stepAttempts'], $city['relationId'], $city['areaId'], $city['preferredProvider'], $city['lastTransientError']);
        return $city;
    }

    private function persist_and_schedule(&$state, $delay_seconds) {
        $delay_seconds = max(1, (int)$delay_seconds);
        $state['updatedAt'] = $this->now_iso();
        $state['nextRunAt'] = time() + $delay_seconds;
        update_option(self::RUN_OPTION, $state, false);
        wp_clear_scheduled_hook(self::CRON_HOOK, array((string)$state['runId']));
        wp_schedule_single_event((int)$state['nextRunAt'], self::CRON_HOOK, array((string)$state['runId']));
    }

    private function finish_state($state) {
        $state['status'] = ((int)($state['errors'] ?? 0) > 0) ? 'completed_with_errors' : 'completed';
        $state['completedAt'] = $this->now_iso();
        $state['updatedAt'] = $state['completedAt'];
        $state['nextRunAt'] = null;
        $state['activeCity'] = null;
        wp_clear_scheduled_hook(self::CRON_HOOK, array((string)($state['runId'] ?? '')));
        return $state;
    }

    private function query_boundary_relation($ags, $admin_level) {
        return '[out:json][timeout:45];'.
            'rel["de:amtlicher_gemeindeschluessel"="'.preg_replace('/[^0-9]/', '', (string)$ags).'"]["boundary"="administrative"]["admin_level"="'.(int)$admin_level.'"];out ids;';
    }

    private function relation_ids($elements) {
        $ids=array();
        foreach ((array)$elements as $el) {
            if (($el['type'] ?? '') !== 'relation' || !isset($el['id']) || !is_numeric($el['id'])) continue;
            $ids[(string)(int)$el['id']] = (int)$el['id'];
        }
        return array_values($ids);
    }

    private function relation_to_area_id($relation_id) {
        return (string)(3600000000 + (int)$relation_id);
    }

    private function area_selector($area_id) {
        $area_id = preg_replace('/[^0-9]/', '', (string)$area_id);
        return 'area('.$area_id.')->.a;';
    }

    private function query_area_check($area_id) {
        return '[out:json][timeout:45];'.$this->area_selector($area_id).'.a out count;';
    }

    private function query_dog_areas($area_id) {
        return '[out:json][timeout:45];'.$this->area_selector($area_id).
            'nwr(area.a)["leisure"="dog_park"]->.dog;.dog out count;';
    }

    private function query_nature_candidates($area_id) {
        return '[out:json][timeout:45];'.$this->area_selector($area_id).
            '(wr(area.a)["name"]["natural"="wood"];wr(area.a)["name"]["landuse"="forest"];wr(area.a)["name"]["boundary"="forest"];'.
            'wr(area.a)["name"]["boundary"="protected_area"];nwr(area.a)["name"]["leisure"~"^(park|nature_reserve)$"];)->.nature;.nature out count;';
    }

    private function query_dog_rule_evidence($area_id) {
        return '[out:json][timeout:45];'.$this->area_selector($area_id).
            '(nwr(area.a)["leisure"~"^(park|nature_reserve)$"]["dog"];nwr(area.a)["leisure"~"^(park|nature_reserve)$"]["dog:conditional"];'.
            'nwr(area.a)["natural"="wood"]["dog"];nwr(area.a)["natural"="wood"]["dog:conditional"];'.
            'nwr(area.a)["landuse"="forest"]["dog"];nwr(area.a)["landuse"="forest"]["dog:conditional"];'.
            'nwr(area.a)["boundary"~"^(forest|protected_area)$"]["dog"];nwr(area.a)["boundary"~"^(forest|protected_area)$"]["dog:conditional"];)->.rules;.rules out count;';
    }

    private function query_trailheads($area_id) {
        return '[out:json][timeout:45];'.$this->area_selector($area_id).
            'nwr(area.a)["highway"="trailhead"]->.trail;.trail out count;';
    }

    private function count_totals($elements) {
        $out=array();
        foreach ((array)$elements as $el) {
            if (($el['type'] ?? '') !== 'count') continue;
            $out[]=(int)($el['tags']['total'] ?? 0);
        }
        return $out;
    }

    private function overpass($query, $preferred_provider = '') {
        $providers = array(
            array('url'=>'https://overpass.private.coffee/api/interpreter','timeout'=>20),
            array('url'=>'https://overpass-api.de/api/interpreter','timeout'=>20),
            array('url'=>'https://maps.mail.ru/osm/tools/overpass/api/interpreter','timeout'=>10),
        );
        $preferred_provider = trim((string)$preferred_provider);
        if ($preferred_provider !== '') {
            $preferred = array();
            $remaining = array();
            foreach ($providers as $provider_config) {
                if ($provider_config['url'] === $preferred_provider) {
                    $provider_config['timeout'] = max(25, (int)$provider_config['timeout']);
                    $preferred[] = $provider_config;
                } else {
                    $remaining[] = $provider_config;
                }
            }
            if ($preferred) $providers = array_merge($preferred, $remaining);
        }
        $attempts=array();
        foreach ($providers as $provider_config) {
            $provider = $provider_config['url'];
            $t=microtime(true);
            $response=wp_remote_post($provider,array(
                'timeout'=>(int)$provider_config['timeout'],'redirection'=>2,
                'headers'=>array('Content-Type'=>'application/x-www-form-urlencoded; charset=UTF-8','User-Agent'=>'RuhrpottGuru-Outdoor-Coverage/'.self::BUILD_VERSION.' (+https://www.ruhrpottguru.de/)'),
                'body'=>array('data'=>$query),
            ));
            $ms=(int)round((microtime(true)-$t)*1000);
            if (is_wp_error($response)) { $attempts[]=array('provider'=>$provider,'status'=>0,'durationMs'=>$ms,'error'=>$response->get_error_message()); continue; }
            $code=(int)wp_remote_retrieve_response_code($response);
            $payload=json_decode(wp_remote_retrieve_body($response),true);
            if ($code>=200 && $code<300 && is_array($payload) && isset($payload['elements']) && is_array($payload['elements']) && empty($payload['remark'])) {
                $attempts[]=array('provider'=>$provider,'status'=>$code,'durationMs'=>$ms,'elements'=>count($payload['elements']));
                return array('elements'=>$payload['elements'],'meta'=>array('provider'=>$provider,'attempts'=>$attempts,'osmBase'=>$payload['osm3s']['timestamp_osm_base'] ?? null,'areasBase'=>$payload['osm3s']['timestamp_areas_base'] ?? null));
            }
            $remark=is_array($payload)?(string)($payload['remark']??''):'';
            $attempts[]=array('provider'=>$provider,'status'=>$code,'durationMs'=>$ms,'error'=>$remark ?: 'invalid_response');
        }
        return new WP_Error('outdoor_coverage_overpass_failed', 'Alle Overpass-Provider fehlgeschlagen.', array('attempts'=>$attempts));
    }
}
