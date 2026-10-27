<?php
/**
 * Plugin Name: PottFuchs P0 – Kategorie-SEO-Titel-Rollback
 * Description: Stellt fünf vorhandene SEO-Titel nur bei unveränderten Zielwerten zurück.
 * Version: 1.1.0
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) { exit; }
function pottfuchs_p0_cat_title_rollback_activate() {
    if (!current_user_can('manage_options')) { return; }
    $manifest = get_option('pottfuchs_p0_cat_titles_110_backup', false);
    if (!is_array($manifest) || count($manifest) !== 5) {
        update_option('pottfuchs_p0_cat_titles_110_rollback_result', 'ABBRUCH: Sicherung fehlt.', false); return;
    }
    foreach ($manifest as $id => $pair) {
        $post = get_post($id);
        if (!in_array((int)$id, array(21,2284,29,2729,2812), true) || !$post || $post->post_type !== 'page' || $post->post_status !== 'publish' || !is_array($pair) || count($pair) !== 2 || (string)get_post_meta($id,'rpg_seo_title',true) !== $pair[1]) {
            update_option('pottfuchs_p0_cat_titles_110_rollback_result', 'ABBRUCH ohne Änderung: Abweichung bei Seite ' . $id, false); return;
        }
    }
    foreach ($manifest as $id => $pair) {
        if (!update_post_meta($id,'rpg_seo_title',$pair[0],$pair[1])) {
            update_option('pottfuchs_p0_cat_titles_110_rollback_result', 'FEHLER bei Seite ' . $id . '; verbleibende Werte vor Wiederholung prüfen.', false); return;
        }
    }
    update_option('pottfuchs_p0_cat_titles_110_rollback_result', 'OK: Fünf ursprüngliche Titel wiederhergestellt.', false);
}
register_activation_hook(__FILE__, 'pottfuchs_p0_cat_title_rollback_activate');
add_action('admin_notices',function(){ if(current_user_can('manage_options')) { $m=get_option('pottfuchs_p0_cat_titles_110_rollback_result','');if($m!=='') echo '<div class="notice notice-info"><p>'.esc_html($m).'</p></div>'; }});
