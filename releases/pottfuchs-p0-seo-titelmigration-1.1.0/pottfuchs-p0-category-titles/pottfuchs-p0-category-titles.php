<?php
/**
 * Plugin Name: PottFuchs P0 – Kategorie-SEO-Titelmigration
 * Description: Einmaliger, versionsgebundener Korrekturlauf für fünf vorhandene rpg_seo_title-Postmetadaten. Kein neuer SEO-Hook.
 * Version: 1.1.0
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) { exit; }

function pottfuchs_p0_cat_title_manifest() {
    return array(
        21 => array('Veranstaltungen im Ruhrgebiet – heute & am Wochenende | RuhrpottGuru', 'Veranstaltungen im Ruhrgebiet – heute & am Wochenende | PottFuchs'),
        2284 => array('Restaurants & Essen im Ruhrgebiet | RuhrpottGuru', 'Restaurants & Essen im Ruhrgebiet | PottFuchs'),
        29 => array('Sehenswürdigkeiten & Highlights im Ruhrgebiet | RuhrpottGuru', 'Sehenswürdigkeiten & Highlights im Ruhrgebiet | PottFuchs'),
        2729 => array('Kultur im Ruhrgebiet – Museen, Theater & mehr | RuhrpottGuru', 'Kultur im Ruhrgebiet – Museen, Theater & mehr | PottFuchs'),
        2812 => array('Freizeitaktivitäten im Ruhrgebiet | RuhrpottGuru', 'Freizeitaktivitäten im Ruhrgebiet | PottFuchs'),
    );
}
function pottfuchs_p0_cat_title_activate() {
    if (!current_user_can('manage_options')) { return; }
    if (get_option('pottfuchs_p0_cat_titles_110_backup', false) !== false) {
        update_option('pottfuchs_p0_cat_titles_110_result', 'ABBRUCH: Sicherung einer früheren Migration vorhanden.', false); return;
    }
    $manifest = pottfuchs_p0_cat_title_manifest();
    foreach ($manifest as $id => $pair) {
        $post = get_post($id);
        if (!$post || $post->post_type !== 'page' || $post->post_status !== 'publish' || (string)get_post_meta($id, 'rpg_seo_title', true) !== $pair[0]) {
            update_option('pottfuchs_p0_cat_titles_110_result', 'ABBRUCH ohne Titeländerung: Quelle abweichend bei Seite ' . $id . '.', false); return;
        }
    }
    // Restore records before any mutation. Never replace unverified values.
    if (!add_option('pottfuchs_p0_cat_titles_110_backup', $manifest, '', false)) {
        update_option('pottfuchs_p0_cat_titles_110_result', 'ABBRUCH: Rollback-Sicherung nicht speicherbar.', false); return;
    }
    $changed = array();
    foreach ($manifest as $id => $pair) {
        if (!update_post_meta($id, 'rpg_seo_title', $pair[1], $pair[0])) {
            foreach ($changed as $revert_id) {
                $prev = $manifest[$revert_id];
                update_post_meta($revert_id, 'rpg_seo_title', $prev[0], $prev[1]);
            }
            update_option('pottfuchs_p0_cat_titles_110_result', 'FEHLER: Schreibvorgang fehlgeschlagen bei Seite ' . $id . '; Rücksetzung versucht, Status prüfen.', false);
            return;
        }
        $changed[] = $id;
    }
    update_option('pottfuchs_p0_cat_titles_110_result', 'OK: Fünf bestehende SEO-Titel-Metadaten angepasst; keine Hooks oder URLs geändert.', false);
}
register_activation_hook(__FILE__, 'pottfuchs_p0_cat_title_activate');
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) { return; }
    $result = get_option('pottfuchs_p0_cat_titles_110_result', '');
    if ($result !== '') { echo '<div class="notice notice-info"><p>' . esc_html($result) . '</p></div>'; }
});
