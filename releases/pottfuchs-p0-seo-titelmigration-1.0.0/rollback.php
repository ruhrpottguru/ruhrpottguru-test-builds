<?php
/**
 * Plugin Name: PottFuchs P0 SEO – Titelmigration Rollback
 * Description: Stellt WPCode #3343 nur wieder her, wenn der Inhalt seit der Migration unverändert ist.
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) exit;
register_activation_hook(__FILE__, function () {
    if (!current_user_can('manage_options')) return;
    $post=get_post(3343);
    $original=get_option('pottfuchs_p0_titles_rollback', null);
    $sha=get_option('pottfuchs_p0_titles_new_sha256', null);
    if (!$post || $post->post_type!=='wpcode' || !is_string($original) || !$sha || !hash_equals($sha,hash('sha256',(string)$post->post_content))) {
        update_option('pottfuchs_p0_titles_rollback_result','Rollback verweigert: Quellstand verändert oder Sicherung fehlt',false);
        return;
    }
    $updated=wp_update_post(array('ID'=>3343,'post_content'=>$original),true);
    if(is_wp_error($updated)) {update_option('pottfuchs_p0_titles_rollback_result','Rollback fehlgeschlagen',false);return;}
    delete_option('wpcode_snippets');
    update_option('pottfuchs_p0_titles_rollback_result','Original WPCode #3343 wiederhergestellt',false);
});
add_action('admin_notices',function(){
  if(current_user_can('manage_options')) { $t=get_option('pottfuchs_p0_titles_rollback_result',''); if($t) echo '<div class="notice notice-info"><p>'.esc_html($t).'</p></div>'; }
});
