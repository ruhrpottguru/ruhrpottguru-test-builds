<?php
/**
 * Plugin Name: PottFuchs P0 SEO – Titelmigration der bestehenden WPCode-Quelle
 * Description: Aktualisiert einmalig nur die fünf kanonischen Kategorie-Titelsuffixe in WPCode #3343; keine neue SEO-Ausgabe.
 * Version: 1.0.0
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) { exit; }

function pottfuchs_p0_titles_patch($old) {
    $prefixes = array(
        'Veranstaltungen im Ruhrgebiet',
        'Restaurants & Essen im Ruhrgebiet',
        'Sehenswürdigkeiten & Highlights im Ruhrgebiet',
        'Kultur im Ruhrgebiet',
        'Freizeitaktivitäten im Ruhrgebiet',
    );
    $found = 0;
    $new = $old;
    foreach ($prefixes as $prefix) {
        $pattern = '/(' . preg_quote($prefix, '/') . '[^\r\n\'\"]{0,90})\s\| RuhrpottGuru/u';
        $next = preg_replace_callback($pattern, static function ($m) use (&$found) {
            ++$found;
            return $m[1] . ' | PottFuchs';
        }, $new, -1, $matches);
        if ($next === null || $matches !== 1) {
            return array('ok' => false, 'code' => $old, 'matched' => $found, 'reason' => 'Expected exactly one canonical title for ' . $prefix);
        }
        $new = $next;
    }
    return array('ok' => $found === 5, 'code' => $new, 'matched' => $found, 'reason' => '');
}

function pottfuchs_p0_titles_activate() {
    if (!current_user_can('manage_options')) return;
    $post = get_post(3343);
    if (!$post || $post->post_type !== 'wpcode' || $post->post_status !== 'publish') {
        update_option('pottfuchs_p0_titles_result', 'Abbruch: WPCode-Owner 3343 nicht aktiv gefunden', false);
        return;
    }
    $before = (string) $post->post_content;
    $result = pottfuchs_p0_titles_patch($before);
    if (!$result['ok']) {
        update_option('pottfuchs_p0_titles_result', 'Abbruch ohne Änderung: ' . $result['reason'], false);
        return;
    }
    if (get_option('pottfuchs_p0_titles_rollback', null) !== null) {
        update_option('pottfuchs_p0_titles_result', 'Abbruch: vorhandenes Rollback verhindert Mehrfachmigration', false);
        return;
    }
    add_option('pottfuchs_p0_titles_rollback', $before, '', false);
    $id = wp_update_post(array('ID' => 3343, 'post_content' => $result['code']), true);
    if (is_wp_error($id)) {
        delete_option('pottfuchs_p0_titles_rollback');
        update_option('pottfuchs_p0_titles_result', 'Abbruch: WordPress-Postupdate fehlgeschlagen', false);
        return;
    }
    delete_option('wpcode_snippets');
    update_option('pottfuchs_p0_titles_new_sha256', hash('sha256', $result['code']), false);
    update_option('pottfuchs_p0_titles_result', 'Korrigiert: fünf Titel in WPCode-Owner 3343; Cache invalidiert', false);
}
register_activation_hook(__FILE__, 'pottfuchs_p0_titles_activate');

function pottfuchs_p0_titles_notice() {
    if (!current_user_can('manage_options')) return;
    $message = get_option('pottfuchs_p0_titles_result', '');
    if ($message === '') return;
    echo '<div class="notice notice-info"><p><strong>PottFuchs P0 SEO: </strong>' . esc_html($message) . '</p></div>';
}
add_action('admin_notices', 'pottfuchs_p0_titles_notice');
