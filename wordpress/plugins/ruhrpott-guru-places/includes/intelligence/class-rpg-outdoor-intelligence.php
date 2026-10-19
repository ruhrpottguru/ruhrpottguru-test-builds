<?php
if (!defined('ABSPATH')) exit;

/**
 * RuhrpottGuru Outdoor Intelligence – Hunde & Wald/Natur dark launch.
 *
 * v1.10.130 is deliberately additive:
 * - four dedicated Outdoor tables with their own schema marker
 * - reuses rg_intelligence_claims / rg_resolved_snapshots for policy evidence
 * - admin-only read API for status/search
 * - no importer, worker, reprocessing job, theme or public search integration
 * - radius/distance always uses a public-ready entry point, never an area centroid
 */
final class RuhrpottGuru_Outdoor_Intelligence {
    const BUILD_VERSION = '1.10.132';
    const SCHEMA_VERSION = '1.0.0';
    const SCHEMA_OPTION = 'rg_outdoor_schema_version';
    const RESOLVER_VERSION = 'outdoor-search-1.0';

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'ensure_schema'), 3);
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    private function table($suffix) {
        global $wpdb;
        return $wpdb->prefix . 'rg_' . $suffix;
    }

    public function ensure_schema() {
        if (get_option(self::SCHEMA_OPTION) === self::SCHEMA_VERSION) return;
        $this->install_schema();
    }

    private function install_schema() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $entities = $this->table('outdoor_entities');
        $sources = $this->table('outdoor_entity_sources');
        $entries = $this->table('outdoor_entries');
        $municipalities = $this->table('outdoor_municipalities');

        $sql = array();
        $sql[] = "CREATE TABLE $entities (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            canonical_key char(40) NOT NULL,
            parent_entity_id bigint(20) unsigned NULL,
            entity_type varchar(32) NOT NULL,
            name varchar(255) NOT NULL,
            normalized_name varchar(255) NOT NULL,
            public_slug varchar(190) NOT NULL DEFAULT '',
            identity_status varchar(32) NOT NULL DEFAULT 'candidate',
            lifecycle_status varchar(32) NOT NULL DEFAULT 'active',
            active tinyint(1) NOT NULL DEFAULT 1,
            quality_score decimal(5,4) NOT NULL DEFAULT 0.0000,
            centroid_lat decimal(10,7) NULL,
            centroid_lng decimal(10,7) NULL,
            bbox_min_lat decimal(10,7) NULL,
            bbox_min_lng decimal(10,7) NULL,
            bbox_max_lat decimal(10,7) NULL,
            bbox_max_lng decimal(10,7) NULL,
            primary_source_key varchar(100) NOT NULL DEFAULT '',
            first_seen_at datetime NOT NULL,
            last_seen_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY canonical_key (canonical_key),
            KEY parent_entity (parent_entity_id),
            KEY public_type (identity_status, active, entity_type),
            KEY lifecycle_status (lifecycle_status),
            KEY name_idx (normalized_name)
        ) $charset;";

        $sql[] = "CREATE TABLE $sources (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_id bigint(20) unsigned NOT NULL,
            source_key varchar(100) NOT NULL,
            source_uid varchar(190) NOT NULL,
            source_url text NULL,
            osm_type char(1) NOT NULL DEFAULT '',
            osm_id bigint(20) unsigned NOT NULL DEFAULT 0,
            osm_version bigint(20) unsigned NOT NULL DEFAULT 0,
            payload_hash char(64) NOT NULL DEFAULT '',
            raw_payload longtext NULL,
            source_status varchar(24) NOT NULL DEFAULT 'active',
            missing_since datetime NULL,
            missing_count smallint unsigned NOT NULL DEFAULT 0,
            observed_at datetime NULL,
            first_seen_at datetime NOT NULL,
            last_seen_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY source_object (source_key, source_uid),
            KEY entity_id (entity_id),
            KEY source_status (source_status),
            KEY osm_object (osm_type, osm_id)
        ) $charset;";

        $sql[] = "CREATE TABLE $entries (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_id bigint(20) unsigned NOT NULL,
            canonical_key char(40) NOT NULL,
            name varchar(255) NOT NULL DEFAULT '',
            entry_type varchar(32) NOT NULL DEFAULT 'trailhead',
            lat decimal(10,7) NOT NULL,
            lng decimal(10,7) NOT NULL,
            municipality_slug varchar(100) NOT NULL DEFAULT '',
            public_ready tinyint(1) NOT NULL DEFAULT 0,
            access_status varchar(32) NOT NULL DEFAULT 'unknown',
            quality_score decimal(5,4) NOT NULL DEFAULT 0.0000,
            route_connected tinyint(1) NOT NULL DEFAULT 0,
            barrier_status varchar(32) NOT NULL DEFAULT 'unknown',
            primary_source_key varchar(100) NOT NULL DEFAULT '',
            primary_source_uid varchar(190) NOT NULL DEFAULT '',
            evidence_json longtext NULL,
            verified_at datetime NULL,
            last_seen_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY canonical_key (canonical_key),
            KEY entity_public (entity_id, public_ready, access_status),
            KEY municipality (municipality_slug),
            KEY lat_idx (lat),
            KEY lng_idx (lng)
        ) $charset;";

        $sql[] = "CREATE TABLE $municipalities (
            entity_id bigint(20) unsigned NOT NULL,
            municipality_slug varchar(100) NOT NULL,
            relation_type varchar(24) NOT NULL DEFAULT 'overlap',
            entry_count smallint unsigned NOT NULL DEFAULT 0,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (entity_id, municipality_slug),
            KEY municipality (municipality_slug),
            KEY relation_type (relation_type)
        ) $charset;";

        foreach ($sql as $statement) dbDelta($statement);

        $required = array($entities, $sources, $entries, $municipalities);
        foreach ($required as $table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return;
        }
        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
    }

    public function register_rest_routes() {
        register_rest_route('ruhrpottguru/v1', '/outdoor/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_status'),
            'permission_callback' => array($this, 'can_manage'),
        ));
        register_rest_route('ruhrpottguru/v1', '/outdoor/search', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_search'),
            'permission_callback' => array($this, 'can_manage'),
        ));
    }

    public function can_manage() {
        return current_user_can('manage_options');
    }

    private function table_exists($table) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public function rest_status() {
        global $wpdb;
        $tables = array(
            'entities' => $this->table('outdoor_entities'),
            'sources' => $this->table('outdoor_entity_sources'),
            'entries' => $this->table('outdoor_entries'),
            'municipalities' => $this->table('outdoor_municipalities'),
        );
        $exists = array();
        $counts = array();
        foreach ($tables as $key => $table) {
            $exists[$key] = $this->table_exists($table);
            $counts[$key] = $exists[$key] ? (int)$wpdb->get_var("SELECT COUNT(*) FROM $table") : 0;
        }
        $schema_ready = !in_array(false, $exists, true) && get_option(self::SCHEMA_OPTION) === self::SCHEMA_VERSION;
        return rest_ensure_response(array(
            'ok' => true,
            'darkLaunch' => true,
            'buildVersion' => self::BUILD_VERSION,
            'schemaVersion' => self::SCHEMA_VERSION,
            'schemaReady' => $schema_ready,
            'tables' => $exists,
            'counts' => $counts,
            'publicIntegration' => false,
            'importerEnabled' => false,
            'workerEnabled' => false,
            'resolverVersion' => self::RESOLVER_VERSION,
        ));
    }

    private function intent_config($intent) {
        $valid = array('forest','dog_any','dog_walk','dog_park','forest_dog','dog_off_leash','forest_dog_off_leash');
        return in_array($intent, $valid, true) ? $intent : '';
    }

    private function number_param($value, $default, $min, $max) {
        if ($value === null || $value === '') return $default;
        if (!is_numeric($value)) return null;
        $n = (float)$value;
        if ($n < $min || $n > $max) return null;
        return $n;
    }

    private function distance_km($lat1, $lng1, $lat2, $lng2) {
        $earth = 6371.0088;
        $p1 = deg2rad((float)$lat1);
        $p2 = deg2rad((float)$lat2);
        $dp = deg2rad((float)$lat2 - (float)$lat1);
        $dl = deg2rad((float)$lng2 - (float)$lng1);
        $a = sin($dp/2)*sin($dp/2) + cos($p1)*cos($p2)*sin($dl/2)*sin($dl/2);
        return $earth * (2 * atan2(sqrt($a), sqrt(max(0.0, 1.0-$a))));
    }

    private function distance_value($km) {
        if ($km <= 1.0) return 1.00;
        if ($km <= 2.5) return 0.92;
        if ($km <= 5.0) return 0.82;
        if ($km <= 10.0) return 0.68;
        if ($km <= 25.0) return 0.48;
        if ($km <= 50.0) return 0.28;
        return 0.10;
    }

    private function policy_value($policy, $intent) {
        if (in_array($intent, array('dog_off_leash','forest_dog_off_leash'), true)) {
            $m = array('designated'=>1.00,'off_leash'=>0.98,'conditional'=>0.62);
            return isset($m[$policy]) ? $m[$policy] : 0.0;
        }
        $m = array('designated'=>1.00,'off_leash'=>0.96,'allowed'=>0.80,'conditional'=>0.68,'leashed'=>0.58);
        return isset($m[$policy]) ? $m[$policy] : 0.0;
    }

    private function weights($intent) {
        if ($intent === 'forest') return array('nature'=>.34,'walk'=>.20,'dog'=>0,'zone'=>0,'evidence'=>.16,'entry'=>.12,'dist'=>.18);
        if ($intent === 'dog_park') return array('nature'=>0,'walk'=>.06,'dog'=>.25,'zone'=>.27,'evidence'=>.16,'entry'=>.08,'dist'=>.18);
        if ($intent === 'dog_walk') return array('nature'=>.19,'walk'=>.25,'dog'=>.20,'zone'=>0,'evidence'=>.14,'entry'=>.08,'dist'=>.14);
        if (in_array($intent, array('forest_dog','forest_dog_off_leash'), true)) return array('nature'=>.23,'walk'=>.16,'dog'=>.27,'zone'=>0,'evidence'=>.14,'entry'=>.08,'dist'=>.12);
        return array('nature'=>.08,'walk'=>.12,'dog'=>.31,'zone'=>.14,'evidence'=>.15,'entry'=>.06,'dist'=>.14);
    }

    private function valid_dog_evidence($policy, $evidence_type, $confidence, $intent, $condition_clear) {
        $positive = array('designated','off_leash','allowed','leashed','conditional');
        if (!in_array($policy, $positive, true)) return false;
        if (in_array($intent, array('dog_off_leash','forest_dog_off_leash'), true)) {
            if (in_array($policy, array('designated','off_leash'), true)) {
                return in_array($evidence_type, array('official_specific','operator_specific','osm_explicit'), true) && $confidence >= .75;
            }
            return $policy === 'conditional' && $condition_clear && in_array($evidence_type, array('official_specific','operator_specific'), true) && $confidence >= .85;
        }
        if ($intent === 'dog_park') {
            return $policy === 'designated' && in_array($evidence_type, array('official_specific','operator_specific','osm_explicit'), true) && $confidence >= .70;
        }
        return in_array($evidence_type, array('official_specific','operator_specific','osm_explicit','legal_baseline'), true) && $confidence >= .65;
    }

    private function hard_filter($candidate, $intent) {
        if (in_array($candidate['access_status'], array('temporarily_closed','permanently_closed','prohibited'), true)) return false;
        if ($intent === 'forest' && $candidate['kind'] !== 'forest') return false;
        if ($intent === 'dog_walk' && !in_array($candidate['kind'], array('forest','nature','park','route'), true)) return false;
        if ($intent === 'dog_park' && $candidate['kind'] !== 'dog_zone') return false;
        if (in_array($intent, array('forest_dog','forest_dog_off_leash'), true) && $candidate['kind'] !== 'forest') return false;
        if ($intent !== 'forest') {
            if (!$this->valid_dog_evidence($candidate['dog_policy'], $candidate['dog_evidence_type'], $candidate['evidence_confidence'], $intent, $candidate['official_condition_clear'])) return false;
        }
        return true;
    }

    private function explanation($candidate, $intent) {
        $labels = array(
            'designated'=>'Hundewiese/Freilauffläche',
            'off_leash'=>'Freilauf bestätigt',
            'allowed'=>'Hunde erlaubt',
            'leashed'=>'Hunde erlaubt – Leine erforderlich',
            'conditional'=>'Freilauf nur unter Bedingungen',
        );
        $out = array();
        if ($intent !== 'forest' && isset($labels[$candidate['dog_policy']])) $out[] = $labels[$candidate['dog_policy']];
        if ($intent !== 'forest' && in_array($candidate['dog_evidence_type'], array('official_specific','operator_specific'), true)) $out[] = 'Offizielle/Betreiber-Quelle';
        if ($candidate['kind'] === 'forest') $out[] = 'Wald/Naturziel';
        if ($candidate['walkability'] >= .8) $out[] = 'Sehr gut zum Spazieren';
        if ($candidate['evidence_confidence'] >= .9) $out[] = 'Regel gut belegt';
        if ($candidate['entry_quality'] >= .9) $out[] = 'Geprüfter Besucherzugang';
        if ($candidate['access_status'] === 'restricted') $out[] = 'Zugang eingeschränkt';
        if (in_array('leash_recommended', $candidate['advisories'], true)) $out[] = 'Hinweis: Leine empfohlen';
        return array_slice($out, 0, 4);
    }

    private function fetch_policy_snapshots($entity_ids) {
        global $wpdb;
        $out = array();
        $entity_ids = array_values(array_unique(array_filter(array_map('absint', (array)$entity_ids))));
        if (!$entity_ids) return $out;
        $table = $this->table('resolved_snapshots');
        if (!$this->table_exists($table)) return $out;
        $placeholders = implode(',', array_fill(0, count($entity_ids), '%d'));
        $args = array_merge(array("SELECT entity_id,resolved_json,resolver_version,valid_until FROM $table WHERE entity_type='outdoor' AND namespace='outdoor_policy' AND entity_id IN ($placeholders)"), $entity_ids);
        $query = call_user_func_array(array($wpdb, 'prepare'), $args);
        $rows = (array)$wpdb->get_results($query, ARRAY_A);
        foreach ($rows as $row) {
            $resolved = json_decode((string)$row['resolved_json'], true);
            if (!is_array($resolved)) $resolved = array();
            $resolved['_resolver_version'] = (string)$row['resolver_version'];
            $resolved['_valid_until'] = (string)($row['valid_until'] ?? '');
            $out[(int)$row['entity_id']] = $resolved;
        }
        return $out;
    }

    private function combine_access_status($entry_status, $policy_status) {
        $entry_status = (string)$entry_status;
        $policy_status = (string)$policy_status;
        $rank = array('permanently_closed'=>5,'temporarily_closed'=>4,'prohibited'=>4,'restricted'=>3,'open'=>2,'unknown'=>1,''=>0);
        $a = isset($rank[$entry_status]) ? $rank[$entry_status] : 1;
        $b = isset($rank[$policy_status]) ? $rank[$policy_status] : 0;
        return $b > $a ? $policy_status : ($entry_status !== '' ? $entry_status : 'unknown');
    }

    public function rest_search(WP_REST_Request $request) {
        global $wpdb;
        $lat = $this->number_param($request->get_param('lat'), null, -90, 90);
        $lng = $this->number_param($request->get_param('lng'), null, -180, 180);
        $radius = $this->number_param($request->get_param('radiusKm'), 10.0, .1, 50.0);
        $limit_num = $this->number_param($request->get_param('limit'), 12, 1, 50);
        $intent = $this->intent_config(sanitize_key((string)$request->get_param('intent')));
        if ($lat === null || $lng === null || $radius === null || $limit_num === null || $intent === '') {
            return new WP_REST_Response(array('ok'=>false,'message'=>'Ungültige Outdoor-Suchparameter.'), 400);
        }
        $limit = (int)$limit_num;

        $entities = $this->table('outdoor_entities');
        $entries = $this->table('outdoor_entries');
        if (!$this->table_exists($entities) || !$this->table_exists($entries)) {
            return new WP_REST_Response(array('ok'=>false,'message'=>'Outdoor-Dark-Launch-Schema ist noch nicht bereit.'), 503);
        }

        $lat_delta = $radius / 110.574;
        $cos = max(.15, cos(deg2rad((float)$lat)));
        $lng_delta = $radius / (111.320 * $cos);
        $candidate_limit = min(400, max(40, $limit * 10));
        $sql = $wpdb->prepare(
            "SELECT e.id AS entry_id,e.entity_id,e.name AS entry_name,e.entry_type,e.lat,e.lng,e.municipality_slug,e.access_status AS entry_access_status,e.quality_score AS entry_quality,e.route_connected,e.barrier_status,e.verified_at,o.parent_entity_id,o.entity_type,o.name,o.public_slug,o.quality_score AS entity_quality,o.primary_source_key FROM $entries e INNER JOIN $entities o ON o.id=e.entity_id WHERE e.public_ready=1 AND e.lat BETWEEN %f AND %f AND e.lng BETWEEN %f AND %f AND o.identity_status='public_ready' AND o.active=1 AND o.lifecycle_status='active' LIMIT %d",
            $lat-$lat_delta, $lat+$lat_delta, $lng-$lng_delta, $lng+$lng_delta, $candidate_limit
        );
        $rows = (array)$wpdb->get_results($sql, ARRAY_A);
        if (!$rows) return rest_ensure_response(array('ok'=>true,'darkLaunch'=>true,'intent'=>$intent,'radiusKm'=>$radius,'count'=>0,'items'=>array(),'resolverVersion'=>self::RESOLVER_VERSION));

        $snapshots = $this->fetch_policy_snapshots(array_column($rows, 'entity_id'));
        $best_by_entity = array();
        foreach ($rows as $row) {
            $distance = $this->distance_km($lat, $lng, (float)$row['lat'], (float)$row['lng']);
            if ($distance > $radius) continue;
            $entity_id = (int)$row['entity_id'];
            if (!isset($best_by_entity[$entity_id]) || $distance < $best_by_entity[$entity_id]['_distance']) {
                $row['_distance'] = $distance;
                $best_by_entity[$entity_id] = $row;
            }
        }
        if (!$best_by_entity) return rest_ensure_response(array('ok'=>true,'darkLaunch'=>true,'intent'=>$intent,'radiusKm'=>$radius,'count'=>0,'items'=>array(),'resolverVersion'=>self::RESOLVER_VERSION));

        $weights = $this->weights($intent);
        $ranked = array();
        foreach ($best_by_entity as $entity_id => $row) {
            $p = isset($snapshots[$entity_id]) ? $snapshots[$entity_id] : array();
            $advisories = isset($p['advisories']) && is_array($p['advisories']) ? array_values($p['advisories']) : array();
            $candidate = array(
                'id'=>$entity_id,
                'parent_id'=>(int)($row['parent_entity_id'] ?? 0),
                'name'=>(string)$row['name'],
                'slug'=>(string)$row['public_slug'],
                'kind'=>(string)$row['entity_type'],
                'dog_policy'=>sanitize_key((string)($p['dog_policy'] ?? 'unknown')),
                'dog_evidence_type'=>sanitize_key((string)($p['dog_evidence_type'] ?? 'unknown')),
                'evidence_confidence'=>max(0.0,min(1.0,(float)($p['evidence_confidence'] ?? 0))),
                'official_condition_clear'=>!empty($p['official_condition_clear']),
                'nature_quality'=>max(0.0,min(1.0,(float)($p['nature_quality'] ?? $row['entity_quality']))),
                'walkability'=>max(0.0,min(1.0,(float)($p['walkability'] ?? $row['entity_quality']))),
                'dog_zone_quality'=>max(0.0,min(1.0,(float)($p['dog_zone_quality'] ?? $row['entity_quality']))),
                'access_status'=>$this->combine_access_status((string)$row['entry_access_status'], (string)($p['access_status'] ?? '')),
                'advisories'=>$advisories,
                'entry_id'=>(int)$row['entry_id'],
                'entry_name'=>(string)$row['entry_name'],
                'entry_type'=>(string)$row['entry_type'],
                'entry_lat'=>(float)$row['lat'],
                'entry_lng'=>(float)$row['lng'],
                'municipality_slug'=>(string)$row['municipality_slug'],
                'entry_quality'=>max(0.0,min(1.0,(float)$row['entry_quality'])),
                'distance_km'=>(float)$row['_distance'],
                'resolver_version'=>(string)($p['_resolver_version'] ?? ''),
            );
            if (!$this->hard_filter($candidate, $intent)) continue;
            $score =
                $weights['nature'] * $candidate['nature_quality'] +
                $weights['walk'] * $candidate['walkability'] +
                $weights['dog'] * $this->policy_value($candidate['dog_policy'], $intent) +
                $weights['zone'] * $candidate['dog_zone_quality'] +
                $weights['evidence'] * $candidate['evidence_confidence'] +
                $weights['entry'] * $candidate['entry_quality'] +
                $weights['dist'] * $this->distance_value($candidate['distance_km']);
            if (in_array($intent, array('dog_off_leash','forest_dog_off_leash'), true) && $candidate['dog_policy'] === 'conditional') $score -= .08;
            $candidate['score'] = round($score, 6);
            $candidate['explanation'] = $this->explanation($candidate, $intent);
            $ranked[] = $candidate;
        }

        usort($ranked, function($a, $b){
            if ($a['score'] !== $b['score']) return $a['score'] < $b['score'] ? 1 : -1;
            if ($a['distance_km'] !== $b['distance_km']) return $a['distance_km'] < $b['distance_km'] ? -1 : 1;
            return strcasecmp($a['name'], $b['name']);
        });

        if ($intent === 'dog_any') {
            $selected = array(); $families = array(); $dog_zones = 0;
            foreach ($ranked as $item) {
                $family = $item['parent_id'] > 0 ? $item['parent_id'] : $item['id'];
                if (isset($families[$family])) continue;
                if ($item['kind'] === 'dog_zone' && $dog_zones >= 2) continue;
                $families[$family] = true;
                if ($item['kind'] === 'dog_zone') $dog_zones++;
                $selected[] = $item;
                if (count($selected) >= $limit) break;
            }
            $ranked = $selected;
        } else {
            $ranked = array_slice($ranked, 0, $limit);
        }

        $items = array();
        foreach ($ranked as $item) {
            $items[] = array(
                'id'=>$item['id'],
                'name'=>$item['name'],
                'slug'=>$item['slug'],
                'kind'=>$item['kind'],
                'distanceKm'=>round($item['distance_km'], 2),
                'score'=>$item['score'],
                'entry'=>array(
                    'id'=>$item['entry_id'],
                    'name'=>$item['entry_name'],
                    'type'=>$item['entry_type'],
                    'lat'=>$item['entry_lat'],
                    'lng'=>$item['entry_lng'],
                    'municipality'=>$item['municipality_slug'],
                    'quality'=>$item['entry_quality'],
                ),
                'dogPolicy'=>$item['dog_policy'],
                'dogEvidenceType'=>$item['dog_evidence_type'],
                'evidenceConfidence'=>$item['evidence_confidence'],
                'accessStatus'=>$item['access_status'],
                'advisories'=>$item['advisories'],
                'explanation'=>$item['explanation'],
            );
        }

        return rest_ensure_response(array(
            'ok'=>true,
            'darkLaunch'=>true,
            'intent'=>$intent,
            'radiusKm'=>$radius,
            'count'=>count($items),
            'items'=>$items,
            'resolverVersion'=>self::RESOLVER_VERSION,
        ));
    }
}
