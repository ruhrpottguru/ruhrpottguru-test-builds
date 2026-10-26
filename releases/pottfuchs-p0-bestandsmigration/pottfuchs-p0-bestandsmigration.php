<?php
/**
 * Plugin Name: PottFuchs P0 – einmalige SEO-Bestandsmigration
 * Description: Einmalige Migration EXISTIERENDER WPCode- und Places-SEO-Owner. Kein eigener SEO-Renderer und kein REST-Änderer. Bricht bei unbekanntem Quellstand vor jeder Änderung ab.
 * Version: 1.0.1
 * Requires PHP: 7.4
 * Author: PottFuchs
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) { exit; }

/** Pure transformation: WPCode source. Does not touch comments, technical IDs or other hooks. */
function pf_p0_patch_wpcode($id, $source) {
    if (!is_string($source) || strlen($source) < 500) {
        throw new RuntimeException('WPCode: unerwartet kurzer Quelltext');
    }
    if ((int)$id === 3343) {
        foreach (array("'Organization'", "'WebSite'", "'ListItem'", 'get_bloginfo', 'rpg_') as $guard) {
            if (strpos($source, $guard) === false) {
                throw new RuntimeException('SEO-Core-Eigentuemer-Signatur fehlt: '.$guard);
            }
        }
        if (substr_count($source, 'auf RuhrpottGuru') !== 1 || substr_count($source, "'name'=>'RuhrpottGuru'") !== 3) {
            throw new RuntimeException('SEO-Core: erwartet exakt einen Fallback und drei Struktur-Namen');
        }
        $changed = str_replace('auf RuhrpottGuru', 'auf PottFuchs', $source);
        $changed = str_replace("'name'=>'RuhrpottGuru'", "'name'=>get_bloginfo('name')", $changed);
        return $changed;
    }
    if ((int)$id === 4102) {
        foreach (array('rpg_seo_v3_event', 'rpg_seo_v3_place', 'rpg_seo_v3_filter') as $guard) {
            if (strpos($source, $guard) === false) {
                throw new RuntimeException('Detail-SEO-Eigentuemer-Signatur fehlt: '.$guard);
            }
        }
        $old = "\$seo_title .= ' | RuhrpottGuru';";
        $new = "\$seo_title .= ' | '.get_bloginfo('name');";
        if (substr_count($source, $old) !== 1 || strpos($source, $new) !== false) {
            throw new RuntimeException('Event-Titel-Suffix nicht eindeutig oder schon veraendert');
        }
        return str_replace($old, $new, $source);
    }
    throw new RuntimeException('Unerwartete WPCode-ID');
}

/** Pure transformation: existing Places owner. No new runtime SEO hook is introduced. */
function pf_p0_patch_places($source) {
    if (!is_string($source) || strlen($source) < 100000) {
        throw new RuntimeException('Places-Hauptdatei zu kurz oder nicht lesbar');
    }
    $startTag='    private function seo_render_detail_html_v101096($data) {';
    $endTag='    public function maybe_render_seo_landing_v101096() {';
    if (substr_count($source, $startTag) !== 1 || substr_count($source, $endTag) !== 1) {
        throw new RuntimeException('Aktueller Places-SEO-Eigentuemer weicht ab');
    }
    $begin=strpos($source, $startTag);
    $end=strpos($source, $endTag, $begin);
    if ($end===false || ($end-$begin)>70000 || $end<=$begin) {
        throw new RuntimeException('SEO-Renderergrenzen stimmen nicht');
    }
    $render=substr($source,$begin,$end-$begin);
    $titleOld="\$title=trim((string)(\$data['title']??''));\$desc=";
    $titleNew="\$title=trim((string)(\$data['title']??''));\$seo_title=trim((string)(\$data['seo_title']??\$title));if(\$seo_title==='')\$seo_title=\$title;\$desc=";
    if (substr_count($render,$titleOld)!==1 || strpos($render,$titleNew)!==false) {
        throw new RuntimeException('SEO-Titel-Eintritt weicht vom geprueften Eigentuemer ab');
    }
    $render=str_replace($titleOld,$titleNew,$render);
    $closureOld='static function () use ($title, $desc, $canonical, $image, $schema_json, $esc)';
    $closureNew='static function () use ($title, $seo_title, $desc, $canonical, $image, $schema_json, $esc)';
    if (substr_count($render,$closureOld)!==1) {
        throw new RuntimeException('SEO-Header-Closure-Owner weicht ab');
    }
    $render=str_replace($closureOld,$closureNew,$render);

    // Exactly three existing canonical title output paths; never touch H1 / schema entity name.
    $hard="esc_html(\$title.' | RuhrpottGuru')";
    $dynamic="esc_html(\$title.' | '.get_bloginfo('name'))";
    $replacement="esc_html(\$seo_title.' | '.get_bloginfo('name'))";
    $nh=substr_count($render,$hard);$nd=substr_count($render,$dynamic);
    if ($nh+$nd!==3 || strpos($render,$replacement)!==false) {
        throw new RuntimeException('Erwartete drei SEO-Titelpfade nicht eindeutig');
    }
    $render=str_replace(array($hard,$dynamic),$replacement,$render);

    // Old hardcoded OG brand only within THIS detail renderer.
    $ogOld='content="RuhrpottGuru"';
    $ogNew='content="\'.esc_attr(get_bloginfo(\'name\')).\'"';
    $ogCount=substr_count($render,$ogOld);
    if ($ogCount!==3 && $ogCount!==0) {
        throw new RuntimeException('Detail-OG-Branding-Muster weicht ab');
    }
    if ($ogCount===3) $render=str_replace($ogOld,$ogNew,$render);
    // Text fallback if still literal; does not alter entity name, classification or metadata shape.
    $descOld="'Entdecke diesen Ort bei RuhrpottGuru.'";
    $descNew="'Entdecke diesen Ort bei '.get_bloginfo('name').'.'";
    if (substr_count($render,$descOld)>1)throw new RuntimeException('Doppelte Detailbeschreibung');
    $render=str_replace($descOld,$descNew,$render);
    $source=substr($source,0,$begin).$render.substr($source,$end);

    // One precise place branch. Event branch is untouched.
    $callOld="\$this->seo_render_detail_html_v101096(array('title'=>\$title,'description'=>\$desc,'full_description'=>\$source_desc,";
    $callNew="\$this->seo_render_detail_html_v101096(array('title'=>\$title,'seo_title'=>((int)\$row['id']===16936 ? 'Zum Schwarzen Essen' : \$title),'description'=>\$desc,'full_description'=>\$source_desc,";
    if (substr_count($source,$callOld)!==1 || strpos($source,$callNew)!==false) {
        throw new RuntimeException('Eindeutiger Ortstitel-Owner nicht auffindbar');
    }
    $source=str_replace($callOld,$callNew,$source);

    // Existing city and share SEO owner: rewrite only exact public OG site-name literals.
    $ogOld='content="RuhrpottGuru"';
    $remaining=substr_count($source,$ogOld);
    if ($remaining>3)throw new RuntimeException('Unerwartete zusaetzliche OG-Quellen');
    $source=str_replace($ogOld,$ogNew,$source);

    // Preserve older public fallback schema brand if exactly as seen in archived Place owner.
    $schemaOld="'name'=>'RuhrpottGuru','url'=>home_url('/')";
    $schemaNew="'name'=>get_bloginfo('name'),'url'=>home_url('/')";
    if (substr_count($source,$schemaOld)>1)throw new RuntimeException('Mehrdeutiger WebSite-Fallback');
    $source=str_replace($schemaOld,$schemaNew,$source);

    if (function_exists('token_get_all')) {
        try { token_get_all($source, TOKEN_PARSE); }
        catch (ParseError $e) { throw new RuntimeException('PHP-Parsefehler nach Patch: '.$e->getMessage()); }
    }
    return $source;
}

function pf_p0_backup_record() {return 'pottfuchs_p0_seo_100_backup';}
function pf_p0_state_record() {return 'pottfuchs_p0_seo_100_state';}
function pf_p0_get_backend_path() {
    return WP_PLUGIN_DIR.'/ruhrpott-guru-places/ruhrpott-guru-places.php';
}
function pf_p0_write_atomic($target,$contents) {
    $dir=dirname($target);
    $temp=tempnam($dir,'pf-p0-');
    if(!$temp)return false;
    try {
        $bytes=file_put_contents($temp,$contents,LOCK_EX);
        if($bytes!==strlen($contents))return false;
        @chmod($temp, fileperms($target)&0777);
        if(!@rename($temp,$target))return false;
        if(function_exists('opcache_invalidate'))@opcache_invalidate($target,true);
        return hash_file('sha256',$target)===hash('sha256',$contents);
    } finally {
        if(is_file($temp))@unlink($temp);
    }
}

function pf_p0_install() {
    if(!current_user_can('activate_plugins'))wp_die('Keine Berechtigung.');
    if(get_bloginfo('name')!=='PottFuchs' || parse_url(home_url('/'),PHP_URL_HOST)!=='www.pottfuchs.de') {
        wp_die('Sicherheitsabbruch: Webseite oder Markenname stimmt nicht. Es wurde nichts geaendert.');
    }
    if(get_option(pf_p0_state_record(),false)) {
        wp_die('Sicherheitsabbruch: Migration wurde bereits ausgefuehrt. Kein zweiter Lauf.');
    }
    if(get_option(pf_p0_backup_record(),false)) {
        wp_die('Sicherheitsabbruch: Vorhandenes Backup muss zuerst geklaert werden.');
    }
    $records=array();$patched=array();
    foreach(array(3343,4102) as $id) {
        $p=get_post($id);
        if(!$p || $p->post_type!=='wpcode' || $p->post_status!=='publish') {
            wp_die('Sicherheitsabbruch: Aktives SEO-Snippet fehlt: '.$id);
        }
        $records[$id]=$p->post_content;
        try { $patched[$id]=pf_p0_patch_wpcode($id,$records[$id]); }
        catch(Throwable $e){wp_die('Sicherheitsabbruch WPCode '.$id.': '.esc_html($e->getMessage()));}
    }
    $path=pf_p0_get_backend_path();
    if(!is_file($path)||!is_readable($path)||!is_writable($path)||!is_writable(dirname($path))) {
        wp_die('Sicherheitsabbruch: aktives Places-Plugin nicht schreibbar.');
    }
    $original=file_get_contents($path);
    if(!is_string($original) || !preg_match('/^\s*\* Version: 1\.10\.188\s*$/mi',substr($original,0,1700))) {
        wp_die('Sicherheitsabbruch: Nur nachgewiesenes Live-Backend 1.10.188 zulaessig. Keine Aenderung.');
    }
    // Verify the declared version and the application's own VERSION constant.
    // The original 1.10.188 backend is archived and checked by this release's CI.
    if (substr_count($original, "const VERSION = '1.10.188'")!==1) {
        wp_die('Sicherheitsabbruch: Places-Versionskonstante entspricht nicht 1.10.188. Keine Aenderung.');
    }
    try { $new=pf_p0_patch_places($original); }
    catch(Throwable $e) { wp_die('Sicherheitsabbruch Places: '.esc_html($e->getMessage())); }

    $backup=array('created'=>gmdate('c'),'plugin_file'=>$path,'file_sha256'=>hash('sha256',$original),
                 'snippets'=>$records,'file'=>$original,'patched_file_sha256'=>hash('sha256',$new));
    if(!add_option(pf_p0_backup_record(),$backup,'','no')) {
        wp_die('Sicherheitsabbruch: Vollstaendiges Backup konnte nicht in WordPress angelegt werden.');
    }
    // Commit existing owner file first; roll back immediately on any subsequent error.
    $written=false;
    try {
        if(hash_file('sha256',$path)!==hash('sha256',$original))throw new RuntimeException('Backend parallel geaendert – Abbruch vor erster Aenderung');
        foreach(array(3343,4102) as $id) {
            $fresh=get_post($id);
            if(!$fresh||$fresh->post_content!==$records[$id])throw new RuntimeException('WPCode parallel geaendert – Abbruch vor erster Aenderung');
        }
        if(!pf_p0_write_atomic($path,$new))throw new RuntimeException('Backend-Quelldatei nicht verifiziert geschrieben');
        $written=true;
        foreach(array(3343,4102) as $id) {
            $saved=wp_update_post(wp_slash(array('ID'=>$id,'post_content'=>$patched[$id])),true);
            if(!$saved || is_wp_error($saved)) {
                throw new RuntimeException('WPCode-Update '.$id.' fehlgeschlagen');
            }
            $actual=get_post($id);
            if(!$actual || $actual->post_content!==$patched[$id]) {
                throw new RuntimeException('WPCode-Update '.$id.' konnte nicht rueckgelesen werden');
            }
        }
        update_option(pf_p0_state_record(),array('date'=>gmdate('c'),
          'plugin_sha256'=>hash('sha256',$new),'snippet3343_sha256'=>hash('sha256',$patched[3343]),
          'snippet4102_sha256'=>hash('sha256',$patched[4102])),'no');
        set_transient('pf_p0_notice','Erfolgreich: bestehende SEO-Owner aktualisiert. Backup verbleibt unter WordPress-Option '.pf_p0_backup_record(),600);
    } catch(Throwable $e) {
        // Best-effort guaranteed record of attempted rollback; never claim success silently.
        $restored=true;
        foreach(array(3343,4102) as $id) {
            $p=get_post($id);
            if($p && $p->post_content!==$records[$id]) {
                $x=wp_update_post(wp_slash(array('ID'=>$id,'post_content'=>$records[$id])),true);
                if(is_wp_error($x)||get_post($id)->post_content!==$records[$id])$restored=false;
            }
        }
        $onDisk=@file_get_contents($path);
        if($onDisk!==$original && !pf_p0_write_atomic($path,$original))$restored=false;
        if($restored){delete_option(pf_p0_backup_record());delete_option(pf_p0_state_record());}
        wp_die('Patch abgebrochen: '.esc_html($e->getMessage()).($restored?' Alle Aenderungen zurueckgerollt.':' Automatisches Rollback unvollstaendig: Backup bewahrt, manuelle Wiederherstellung notwendig.'));
    }
}
register_activation_hook(__FILE__,'pf_p0_install');

// No frontend SEO hooks or API endpoints. Existing owners remain sole publishers.
add_action('admin_notices',function(){
    if(!current_user_can('manage_options'))return;
    $notice=get_transient('pf_p0_notice');
    if($notice){delete_transient('pf_p0_notice');echo '<div class="notice notice-success"><p>'.esc_html($notice).'</p></div>';}
});

add_action('admin_menu',function(){
    add_management_page('PottFuchs P0 Rollback','PottFuchs P0 Rollback','manage_options','pottfuchs-p0-rollback',function(){
        if(!current_user_can('manage_options'))return;
        $backup=get_option(pf_p0_backup_record(),false);
        echo '<div class="wrap"><h1>PottFuchs P0 – Rollback</h1>';
        if(!$backup){echo '<p>Kein Backup vorhanden.</p></div>';return;}
        echo '<p>Backup: '.esc_html((string)$backup['created']).'. Wiederherstellung betrifft nur die zwei WPCode-Quelltexte und die zuvor vorhandene Places-Hauptdatei.</p>';
        echo '<form method="post">';wp_nonce_field('pf_p0_rollback_action');echo '<input type="hidden" name="pf_p0_action" value="rollback">';submit_button('Letzte Migration rueckgaengig machen','secondary');echo '</form></div>';
    });
});
/** Rollback pure admin-side operation, guarded by exact post-migration SHA-256. */
function pf_p0_restore_backup() {
    $backup=get_option(pf_p0_backup_record(),false);
    $state=get_option(pf_p0_state_record(),false);
    if(!is_array($backup)||!is_array($state))throw new RuntimeException('Keine dokumentierte erfolgreiche Migration zum Rollback');
    $path=pf_p0_get_backend_path();
    $current=@file_get_contents($path);
    if(!is_string($current)||hash('sha256',$current)!==$state['plugin_sha256']) {
        throw new RuntimeException('Places-Datei nach der Migration anderweitig veraendert; Rollback verweigert');
    }
    foreach(array(3343,4102) as $id) {
        $p=get_post($id);
        if(!$p||hash('sha256',$p->post_content)!==$state['snippet'.$id.'_sha256']) {
            throw new RuntimeException('WPCode-Snippet '.$id.' zwischenzeitlich veraendert; Rollback verweigert');
        }
    }
    if(!pf_p0_write_atomic($path,$backup['file'])) {
        throw new RuntimeException('Originaldatei konnte nicht wiederhergestellt werden; Backup bewahrt');
    }
    foreach(array(3343,4102) as $id) {
        $r=wp_update_post(wp_slash(array('ID'=>$id,'post_content'=>$backup['snippets'][$id])),true);
        if(!$r || is_wp_error($r) || get_post($id)->post_content!==$backup['snippets'][$id]) {
            throw new RuntimeException('Rollback unvollstaendig: Backup bewahrt, bitte WordPress-Code pruefen');
        }
    }
    delete_option(pf_p0_state_record());delete_option(pf_p0_backup_record());
    return true;
}
add_action('admin_init',function(){
    if(!isset($_POST['pf_p0_action'])||$_POST['pf_p0_action']!=='rollback')return;
    if(!current_user_can('manage_options')||!check_admin_referer('pf_p0_rollback_action'))wp_die('Berechtigung fehlt');
    try {pf_p0_restore_backup();}
    catch(Throwable $e) {wp_die(esc_html($e->getMessage()));}
    wp_safe_redirect(admin_url('plugins.php?pf_p0_rollback=done'));exit;
});