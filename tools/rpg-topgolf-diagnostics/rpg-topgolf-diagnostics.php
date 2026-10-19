<?php
/**
 * Plugin Name: RuhrpottGuru Topgolf Diagnose (nur lesend)
 * Description: Administrativer Einmalbericht für die drei bestehenden Topgolf-IDs. Keine Datenkorrektur und keine externe Übertragung.
 * Version: 1.0.0
 * Author: RuhrpottGuru
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) exit;
final class RPG_Topgolf_Diagnostics {
    public static function boot() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_rpg_topgolf_report', array(__CLASS__, 'download'));
    }
    public static function menu() {
        add_management_page('Topgolf-Prüfung', 'Topgolf-Prüfung', 'manage_options', 'rpg-topgolf-check', array(__CLASS__, 'screen'));
    }
    public static function screen() {
        if (!current_user_can('manage_options')) wp_die('Keine Berechtigung.', '', array('response'=>403));
        echo '<div class="wrap"><h1>Topgolf-Prüfung</h1><p>Dieser Bericht liest ausschließlich die Topgolf-IDs 8577, 8274 und 8576, deren Verknüpfungen sowie Versions- und Codeprüfsummen. Keine Korrektur, keine neuen Schnittstellen und keine Übertragung an externe Dienste.</p><p>Der Bericht enthält keine Zugangsdaten, Benutzerlisten oder privaten Einreichungstexte. Nach dem Download kannst du dieses Diagnose-Plugin deaktivieren und löschen.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="rpg_topgolf_report">';
        wp_nonce_field('rpg_topgolf_report');
        submit_button('Topgolf-Prüfbericht herunterladen');
        echo '</form></div>';
    }
    private static function tags($tags) {
        $out = array();
        foreach (array('name','sport','golf','leisure','building','access','fee','reservation','booking','booking:website','operator:type','opening_hours','website','addr:city','addr:street','addr:housenumber','addr:postcode','indoor','covered') as $key) {
            if (isset($tags[$key]) && is_scalar($tags[$key])) $out[$key] = (string)$tags[$key];
        }
        return $out;
    }
    public static function report() {
        if (!current_user_can('manage_options')) wp_die('Keine Berechtigung.', '', array('response'=>403));
        global $wpdb;
        $out = array('report'=>'rpg-topgolf-read-only','version'=>'1.0.0','generated_at'=>gmdate('c'),'wordpress_version'=>get_bloginfo('version'),'backend'=>array(),'places'=>array(),'errors'=>array());
        if (!class_exists('RuhrpottGuru_Places_Backend')) { $out['errors'][]='Places-Backend nicht aktiv'; return $out; }
        $class = new ReflectionClass('RuhrpottGuru_Places_Backend');
        $file = $class->getFileName();
        $out['backend']['version'] = $class->getConstant('VERSION');
        $out['backend']['sha256'] = is_readable($file) ? hash_file('sha256',$file) : null;
        if (is_readable($file)) {
            $lines = file($file);
            foreach (array('rest_global_search','global_search_place_projection_v101157','seo_public_place_row_v101096','seo_public_place_category_v101096','activity_v2_profile','activity_v2_public_projection','rows_to_elements') as $name) {
                if (!$class->hasMethod($name)) continue;
                $method = $class->getMethod($name);
                $out['backend']['method_sha256'][$name] = hash('sha256',implode('',array_slice($lines,$method->getStartLine()-1,$method->getEndLine()-$method->getStartLine()+1)));
            }
        }
        $backend = $class->newInstanceWithoutConstructor();
        $table = $wpdb->prefix.'rg_places';
        $rows = $wpdb->get_results("SELECT * FROM $table WHERE id IN (8577,8274,8576) ORDER BY id", ARRAY_A);
        if ($wpdb->last_error) { $out['errors'][]='Places-Abfrage fehlgeschlagen'; return $out; }
        foreach ((array)$rows as $row) {
            $id = (int)$row['id']; $item = array();
            foreach (array('id','name','city','street','house_number','postcode','lat','lng','primary_category','primary_subcategory','primary_osm_type','primary_osm_id','active','lifecycle_status','quality_score','updated_at') as $key) $item[$key] = $row[$key] ?? null;
            $item['tags'] = self::tags((array)json_decode((string)($row['raw_tags'] ?? ''),true));
            foreach (array('activity_v2_profile'=>'activity_profile','seo_public_place_category_v101096'=>'seo_category') as $method=>$label) {
                try {
                    if (!$class->hasMethod($method)) continue;
                    $probe = $class->getMethod($method);
                    $probe->setAccessible(true);
                    $result = $probe->invoke($backend,$row);
                    if ($label === 'activity_profile') $result = array_intersect_key((array)$result,array_flip(array('admission','primary_experience','hard_blockers','reasons')));
                    $item[$label] = $result;
                } catch (Throwable $e) { $item[$label] = array('error'=>'Prüfung nicht ausführbar','type'=>get_class($e)); }
            }
            foreach (array('place_sources','place_categories','place_links','place_schedules','place_media','place_enrichment','place_overrides','place_override_submissions','place_media_submissions','wurst_candidates') as $suffix) {
                $name = $wpdb->prefix.'rg_'.$suffix;
                if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($name))) !== $name) continue;
                $item['relation_counts'][$suffix] = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $name WHERE place_id=%d",$id));
            }
            $sources = $wpdb->prefix.'rg_place_sources';
            $item['sources'] = $wpdb->get_results($wpdb->prepare("SELECT source_type,source_uid,osm_type,osm_id FROM $sources WHERE place_id=%d LIMIT 20",$id),ARRAY_A);
            $overrides = $wpdb->prefix.'rg_place_overrides';
            $override = $wpdb->get_row($wpdb->prepare("SELECT override_data,status,source_type,verified_at FROM $overrides WHERE place_id=%d",$id),ARRAY_A);
            if ($override) {
                $data = (array)json_decode((string)$override['override_data'],true);
                $item['override'] = array('status'=>$override['status'],'source_type'=>$override['source_type'],'verified_at'=>$override['verified_at'],'keys'=>array_keys($data),'public_tags'=>self::tags($data));
            }
            $out['places'][] = $item;
        }
        return $out;
    }
    public static function download() {
        if (!current_user_can('manage_options')) wp_die('Keine Berechtigung.', '', array('response'=>403));
        check_admin_referer('rpg_topgolf_report');
        $report = self::report();
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="RuhrpottGuru-Topgolf-Livebefund.json"');
        echo wp_json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
}
RPG_Topgolf_Diagnostics::boot();
