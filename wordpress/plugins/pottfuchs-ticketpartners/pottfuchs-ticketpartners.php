<?php
/**
 * Plugin Name: PottFuchs Ticketpartner (Backend)
 * Description: Rechtegesicherte, standardmäßig inaktive Ticketpartner-Datenebene für die vorhandene PottFuchs-Eventarchitektur.
 * Version: 0.4.0
 * Requires PHP: 8.0
 * Author: PottFuchs
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) exit;

final class PFT_Ticketpartners {
    const VERSION = '0.4.0';
    const DB_VERSION = '2';
    const CRON_HOOK = 'pft_partner_refresh_daily';
    private static $instance;
    public static function instance() { return self::$instance ?: (self::$instance = new self()); }
    private function __construct() {
        add_action('rest_api_init', array($this, 'register_rest'));
        add_filter('rest_post_dispatch', array($this, 'filter_events_response'), 20, 3);
        add_action(self::CRON_HOOK, array($this, 'cron_refresh'));
        add_action('init', array($this, 'maybe_schedule'));
        add_action('plugins_loaded', array($this, 'maybe_upgrade_db'));
        add_action('admin_menu', array($this, 'admin_menu'));
        if (defined('WP_CLI') && WP_CLI) WP_CLI::add_command('pftickets', 'PFT_Ticketpartners_CLI');
    }
    public function maybe_upgrade_db() {
        if (get_option('pft_ticketpartners_db_version') !== self::DB_VERSION) self::install();
    }
    public static function table() { global $wpdb; return $wpdb->prefix . 'pft_partner_occurrences'; }
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table(); $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            partner varchar(32) NOT NULL,
            external_id varchar(64) NOT NULL,
            identity_key char(40) NOT NULL,
            title varchar(255) NOT NULL,
            venue varchar(255) NOT NULL DEFAULT '',
            city varchar(120) NOT NULL,
            postcode varchar(16) NOT NULL DEFAULT '',
            lat decimal(10,7) NULL,
            lng decimal(10,7) NULL,
            start_at datetime NOT NULL,
            start_ts bigint(20) NOT NULL,
            affiliate_url varchar(700) NOT NULL,
            image_url varchar(700) NOT NULL DEFAULT '',
            genre varchar(64) NOT NULL DEFAULT '',
            run_token char(40) NOT NULL,
            PRIMARY KEY  (partner,external_id),
            KEY valid_city (partner,city,start_ts),
            KEY identity_date (identity_key,start_ts),
            KEY current_run (partner,run_token)
        ) $charset;");
        update_option('pft_ticketpartners_db_version', self::DB_VERSION, false);
        if (get_option('pft_ticketpartners_last_good', null) === null) add_option('pft_ticketpartners_last_good', array(), '', false);
    }
    public static function uninstall() { /* Safe rollback: never drop data automatically. */ }
    public static function supported() { return array('reservix', 'eventim'); }
    public static function storage_approved($partner) {
        // Code/config constant, NOT a toggle exposed to public REST.
        $name = 'PFT_' . strtoupper($partner) . '_STORAGE_APPROVED';
        return in_array($partner, self::supported(), true) && defined($name) && constant($name) === true;
    }
    public static function public_approved() {
        return defined('PFT_TICKETPARTNERS_PUBLIC_APPROVED') && PFT_TICKETPARTNERS_PUBLIC_APPROVED === true;
    }
    public static function feed_url($partner) {
        if (!self::storage_approved($partner)) return '';
        $name = 'PFT_' . strtoupper($partner) . '_FEED_URL';
        return defined($name) ? trim((string)constant($name)) : '';
    }
    public static function normalize($value) {
        // Mirrors the existing Places event_listing_norm() identity fields, but is NOT a new admission/ranking rule.
        $text = trim(wp_strip_all_tags((string)$value));
        $text = remove_accents(mb_strtolower($text, 'UTF-8'));
        $text = preg_replace('/[^a-z0-9]+/u', ' ', (string)$text);
        return trim(preg_replace('/\s+/u', ' ', (string)$text));
    }
    public static function identity($title, $city, $venue) {
        $t = self::normalize($title); $c = self::normalize($city); $v = self::normalize($venue);
        if ($t === '' || $c === '') return '';
        return sha1($t . '|' . $c . '|' . ($v ?: 'city'));
    }
    public static function region_cities() {
        // Canonical 53 Ruhrgebiets-Kommunen. City aliases below may handle feed-specific spelling only.
        return array('Bochum','Bottrop','Dortmund','Duisburg','Essen','Gelsenkirchen','Hagen','Hamm','Herne','Mülheim an der Ruhr','Oberhausen',
          'Breckerfeld','Ennepetal','Gevelsberg','Hattingen','Herdecke','Schwelm','Sprockhövel','Wetter (Ruhr)','Witten',
          'Castrop-Rauxel','Datteln','Dorsten','Gladbeck','Haltern am See','Herten','Marl','Oer-Erkenschwick','Recklinghausen','Waltrop',
          'Bergkamen','Bönen','Fröndenberg/Ruhr','Holzwickede','Kamen','Lünen','Schwerte','Selm','Unna','Werne',
          'Alpen','Dinslaken','Hamminkeln','Hünxe','Kamp-Lintfort','Moers','Neukirchen-Vluyn','Rheinberg','Schermbeck','Sonsbeck','Voerde','Wesel','Xanten');
    }
    public static function city_map() {
        $map = array(); foreach (self::region_cities() as $city) $map[self::normalize($city)] = $city;
        foreach (array('muelheim an der ruhr'=>'Mülheim an der Ruhr', 'mulheim an der ruhr'=>'Mülheim an der Ruhr',
          'mülheim'=>'Mülheim an der Ruhr', 'muelheim'=>'Mülheim an der Ruhr', 'voerde niederrhein'=>'Voerde',
          'wetter ruhr'=>'Wetter (Ruhr)', 'frondenberg ruhr'=>'Fröndenberg/Ruhr', 'frondenberg'=>'Fröndenberg/Ruhr',
          'fröndenberg'=>'Fröndenberg/Ruhr', 'haltern'=>'Haltern am See', 'hamm westfalen'=>'Hamm') as $alias=>$canonical) {
            $map[self::normalize($alias)] = $canonical;
        }
        return $map;
    }
    public static function valid_affiliate($url, $partner) {
        $parts = wp_parse_url(trim((string)$url));
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https') return false;
        if (strtolower((string)($parts['host'] ?? '')) !== 'www.awin1.com') return false;
        if (($parts['path'] ?? '') !== '/pclick.php') return false;
        if (isset($parts['user']) || isset($parts['pass'])) return false;
        parse_str((string)($parts['query'] ?? ''), $q);
        // Never substitute a merchant URL for the actual affiliate link.
        return isset($q['p'],$q['a'],$q['m']) && ctype_digit((string)$q['p']) && ctype_digit((string)$q['a']) && ctype_digit((string)$q['m']) &&
            ($partner !== 'reservix' || (string)$q['m'] === '31293');
    }
    public static function row_from_csv($r, $partner, $now) {
        if ($partner !== 'reservix') return null; // Eventim requires its actual feed schema; do not invent.
        $map = self::city_map();
        $city = $map[self::normalize($r['Tickets:event_location_city'] ?? '')] ?? '';
        if (!$city) return null;
        $title = trim(wp_strip_all_tags((string)(($r['Tickets:event_name'] ?? '') ?: ($r['product_name'] ?? ''))));
        if ($title === '' || preg_match('/\b(gutschein|geschenkkarte|treue.ticket|spar.paket)\b/iu', $title)) return null;
        $date = trim((string)($r['Tickets:event_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(?::\d{2})?$/D', $date)) return null;
        $zone = new DateTimeZone('Europe/Berlin');
        $format = strlen($date) === 16 ? '!Y-m-d H:i' : '!Y-m-d H:i:s';
        $dt = DateTimeImmutable::createFromFormat($format, $date, $zone);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$dt || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $dt->format(strlen($date)===16?'Y-m-d H:i':'Y-m-d H:i:s') !== $date) return null;
        // Without an end time, disappear conservatively at the announced start.
        $timestamp = $dt->getTimestamp(); if ($timestamp <= $now || $timestamp > $now + (366 * 86400)) return null;
        if (trim((string)($r['is_for_sale'] ?? '')) !== '1' || trim((string)($r['in_stock'] ?? '')) !== '1') return null;
        $url = trim((string)($r['aw_deep_link'] ?? '')); if (!self::valid_affiliate($url, $partner)) return null;
        $external = trim((string)($r['aw_product_id'] ?? '')); if (!preg_match('/^\d{1,32}$/D',$external)) return null;
        $venue = trim(wp_strip_all_tags((string)($r['Tickets:venue_name'] ?? '')));
        $identity = self::identity($title, $city, $venue); if (!$identity) return null;
        $image = trim((string)($r['merchant_image_url'] ?? ''));
        $parts = wp_parse_url($image);
        $image = defined('PFT_RESERVIX_IMAGE_HOTLINK_APPROVED') && PFT_RESERVIX_IMAGE_HOTLINK_APPROVED === true && is_array($parts) && ($parts['scheme']??'')==='https' && strtolower((string)($parts['host']??''))==='cdn.reservix.com' ? $image : '';
        $rawLat=trim((string)($r['Tickets:latitude']??''));$rawLng=trim((string)($r['Tickets:longitude']??''));
        $lat=is_numeric($rawLat)?(float)$rawLat:null; $lng=is_numeric($rawLng)?(float)$rawLng:null;
        if($lat===null||$lng===null||$lat<50.0||$lat>53.0||$lng<5.0||$lng>9.5){$lat=null;$lng=null;}
        return array('partner'=>$partner,'external_id'=>$external,'identity_key'=>$identity,
          'title'=>mb_substr($title,0,255),'venue'=>mb_substr($venue,0,255),'city'=>$city,
          'postcode'=>mb_substr(trim((string)($r['Tickets:event_location_zipcode']??'')),0,16), 'lat'=>$lat,'lng'=>$lng,
          'start_at'=>$dt->format('Y-m-d H:i:s'), 'start_ts'=>$timestamp,
          'affiliate_url'=>$url,'image_url'=>$image,
          'genre'=>mb_substr(trim(wp_strip_all_tags((string)($r['Tickets:genre']??''))),0,64));
    }

    // ---- 0.2.0: toleranter Abgleich mit bestehenden Events (nur Lesen, kein Schreiben in rg_events) ----
    // Zeiten: rg_events.start_at ist UTC; Partner start_ts ist ein echter Zeitstempel. Beide werden als Zeitstempel verglichen.
    const MATCH_WINDOW = 10800; // 3 Stunden: deckt Einlass-/Beginnzeiten ab.
    public static function genre_denied($genre) {
        $deny = (defined('PFT_RESERVIX_GENRE_DENY') && is_array(PFT_RESERVIX_GENRE_DENY)) ? PFT_RESERVIX_GENRE_DENY
            : array('Gutscheine'); // Entscheidung Nutzer 03.11.2026: nur Gutscheine; per PFT_RESERVIX_GENRE_DENY änderbar.
        return in_array((string)$genre, $deny, true);
    }
    public static function title_variants($title) {
        $t = trim((string)$title);
        $t = preg_replace('/\b(ausverkauft|verlegt[^|]*|zusatztermin|zusatzshow)\b/iu', ' ', $t);
        $parts = array($t);
        foreach (preg_split('/\s+[-–—]\s+|:\s+|\s+\/\s+/u', $t) as $part) { if (trim($part) !== '') $parts[] = $part; }
        $out = array();
        foreach ($parts as $part) { $n = self::normalize($part); if (mb_strlen($n, 'UTF-8') >= 3) $out[$n] = true; }
        return array_keys($out);
    }
    public static function titles_match($a, $b) {
        $best = 0; $va = self::title_variants($a); $vb = self::title_variants($b);
        if ($va && $vb && $va[0] === $vb[0]) return 2;
        foreach ($va as $x) foreach ($vb as $y) { if ($x === $y) $best = 1; }
        foreach ($va as $x) foreach ($vb as $y) {
            $short = mb_strlen($x,'UTF-8') <= mb_strlen($y,'UTF-8') ? $x : $y; $long = $short === $x ? $y : $x;
            if (mb_strlen($short,'UTF-8') >= 4 && preg_match('/(^| )' . preg_quote($short, '/') . '( |$)/u', $long)) $best = 1;
            elseif ($best < 1) { similar_text($x, $y, $pct); if ($pct >= 85) $best = 1; }
        }
        return $best;
    }
    public static function venues_match($a, $b, $city = '') {
        $x = self::normalize($a); $y = self::normalize($b); $c = self::normalize($city);
        if ($c !== '') { $x = trim(preg_replace('/(^| )' . preg_quote($c, '/') . '( |$)/u', ' ', $x)); $y = trim(preg_replace('/(^| )' . preg_quote($c, '/') . '( |$)/u', ' ', $y)); }
        if ($x === '' || $y === '') return true;
        if ($x === $y || strpos($x, $y) !== false || strpos($y, $x) !== false) return true;
        $tx = array_filter(explode(' ', $x), function($w){return mb_strlen($w,'UTF-8')>=4 && !in_array($w,array('stadthalle','theater','halle','haus','zentrum','kulturzentrum','kirche','saal'),true);});
        $ty = explode(' ', $y);
        if (count(array_intersect($tx, $ty)) > 0) return true;
        foreach ($tx as $w) foreach ($ty as $z) { if (mb_strlen($z,'UTF-8') >= 4 && (strpos($w, $z) === 0 || strpos($z, $w) === 0) && !in_array($z,array('stadthalle','theater','halle','haus','zentrum','kulturzentrum','kirche','saal'),true)) return true; }
        return false;
    }
    public static function same_occurrence($pTitle, $pVenue, $pTs, $eTitle, $eVenue, $eTs, $city = '') {
        if (!$pTs || !$eTs || abs((int)$pTs - (int)$eTs) > self::MATCH_WINDOW) return false;
        $m = self::titles_match($pTitle, $eTitle);
        if (!$m) return false;
        return $m === 2 || self::venues_match($pVenue, $eVenue, $city);
    }
    public static function has_existing_match($row, $existingByDay) {
        $day = (int)floor((int)$row['start_ts'] / 86400);
        foreach (array($day-1,$day,$day+1) as $d) foreach (($existingByDay[$d] ?? array()) as $e)
            if (self::same_occurrence($row['title'],$row['venue'],$row['start_ts'],$e['title'],$e['venue'],$e['ts'],(string)($row['city'] ?? ''))) return true;
        return false;
    }
    public static function inspect_csv($file, $partner, $now = null) {
        if ($partner !== 'reservix') throw new RuntimeException('Eventim-Adapter erst nach Prüfung des echten Eventim-Feeds.');
        if (!is_file($file) || !is_readable($file)) throw new RuntimeException('Feed-Datei nicht lesbar.');
        // 0.2.1: Awin liefert je nach Feed-Link gzip. Erkennung am Dateikopf, Entpacken als Datenstrom (keine zweite Datei).
        $magic = (string)file_get_contents($file, false, null, 0, 2);
        $h = fopen($magic === "\x1f\x8b" ? 'compress.zlib://' . $file : $file, 'rb'); if (!$h) throw new RuntimeException('Feed-Datei kann nicht geöffnet werden.');
        try {
            $headers = fgetcsv($h, 0, ',', '"', '');
            $required = array('aw_deep_link','aw_product_id','product_name','Tickets:event_date','Tickets:event_location_city','Tickets:venue_name','is_for_sale','in_stock');
            if (!$headers || count($headers) !== count(array_unique($headers)) || array_diff($required, $headers)) throw new RuntimeException('Feed-Spalten fehlen oder sind doppelt.');
            $records = array(); $stats=array('total'=>0,'eligible'=>0,'invalid'=>0,'duplicates'=>0,'cities'=>array());
            $now = $now === null ? time() : (int)$now;
            while (($cols=fgetcsv($h, 0, ',', '"', ''))!==false) {
                if (count($cols)===1 && trim((string)$cols[0])==='') continue;
                $stats['total']++;
                if (count($cols)!==count($headers)) { $stats['invalid']++; continue; }
                $r = array_combine($headers,$cols); $normalized=self::row_from_csv($r,$partner,$now);
                if (!$normalized) continue;
                $id=$normalized['external_id'];
                if (isset($records[$id])) $stats['duplicates']++;
                $records[$id]=$normalized;
                if (count($records)>25000) throw new RuntimeException('Zu viele regionale Datensätze; Import verweigert.');
            }
            if ($stats['invalid'] > max(5, (int)floor($stats['total'] * 0.01))) throw new RuntimeException('Zu viele beschädigte CSV-Zeilen, kein Import.');
            $stats['eligible']=count($records);
            foreach ($records as $r) $stats['cities'][$r['city']]=($stats['cities'][$r['city']]??0)+1;
            ksort($stats['cities']);
            return array('records'=>$records,'stats'=>$stats);
        } finally { fclose($h); }
    }
    public static function commit_csv($file, $partner) {
        if (!self::storage_approved($partner)) throw new RuntimeException('SCHREIBSTOP: schriftliche Speicherfreigabe noch nicht als Konfigurationsfreigabe bestätigt.');
        $result = self::inspect_csv($file,$partner);
        $count = $result['stats']['eligible'];
        if ($count<1) throw new RuntimeException('Keine zulässigen zukünftigen Regionaldaten; keine Datenbankänderung.');
        global $wpdb; $table=self::table();
        $previous=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE partner=%s",$partner));
        if ($previous>=30 && $count < (int)ceil($previous*0.35)) throw new RuntimeException('Ungewöhnlich starke Feed-Schrumpfung; kein destruktiver Import.');
        $token=sha1($partner.'|'.microtime(true).'|'.wp_rand());
        if ($wpdb->query('START TRANSACTION') === false) throw new RuntimeException('Transaktion konnte nicht gestartet werden.');
        try {
            foreach ($result['records'] as $row) {
                $row['run_token']=$token;
                $cols=array_keys($row); $formats=array(); $values=array();
                foreach($cols as $col){if($row[$col]===null){$formats[]='NULL';}else{$formats[]='%s';$values[]=$row[$col];}}
                $update=array(); foreach($cols as $col) if($col!=='partner'&&$col!=='external_id') $update[]="`$col`=VALUES(`$col`)";
                $sql="INSERT INTO $table (`".implode('`,`',$cols)."`) VALUES (".implode(',',$formats).") ON DUPLICATE KEY UPDATE ".implode(',',$update);
                $ok=$wpdb->query($wpdb->prepare($sql,$values));
                if ($ok===false) throw new RuntimeException('Datenbankimport fehlgeschlagen.');
            }
            $deleted=$wpdb->query($wpdb->prepare("DELETE FROM $table WHERE partner=%s AND (run_token<>%s OR start_ts<=%d)",$partner,$token,time()));
            if ($deleted===false) throw new RuntimeException('Bereinigung fehlgeschlagen.');
            if ($wpdb->query('COMMIT') === false) throw new RuntimeException('Datenbankcommit fehlgeschlagen.');
        } catch (Throwable $e) { $wpdb->query('ROLLBACK'); throw $e; }
        $good=get_option('pft_ticketpartners_last_good',array()); if(!is_array($good))$good=array();
        $good[$partner]=array('at'=>time(),'count'=>$count,'removed'=>$deleted,'total'=>$result['stats']['total']);
        update_option('pft_ticketpartners_last_good',$good,false);
        return array_merge($result['stats'],array('removed'=>$deleted));
    }
    public function maybe_schedule() {
        if (!defined('PFT_RESERVIX_AUTO_REFRESH_APPROVED') || PFT_RESERVIX_AUTO_REFRESH_APPROVED !== true || !self::storage_approved('reservix') || !self::feed_url('reservix')) return;
        if (!wp_next_scheduled(self::CRON_HOOK)) wp_schedule_event(time()+300,'daily',self::CRON_HOOK);
    }
    public function cron_refresh() {
        // Never log or expose feed URLs, they may carry affiliate credentials.
        if (!defined('PFT_RESERVIX_AUTO_REFRESH_APPROVED') || PFT_RESERVIX_AUTO_REFRESH_APPROVED !== true) return;
        foreach (array('reservix') as $partner) {
            $url = self::feed_url($partner);
            $p = wp_parse_url($url);
            if (!$url || !is_array($p) || ($p['scheme']??'')!=='https' || !in_array(strtolower((string)($p['host']??'')),array('productdata.awin.com','www.awin1.com','awin1.com'),true)) continue;
            if (!function_exists('wp_tempnam')) require_once ABSPATH.'wp-admin/includes/file.php';
            $tmp=wp_tempnam('pft-partner-feed.csv'); if(!$tmp)continue;
            try {
                $response=wp_safe_remote_get($url,array('timeout'=>120,'redirection'=>2,'stream'=>true,'filename'=>$tmp,'limit_response_size'=>150*1024*1024));
                if (is_wp_error($response) || (int)wp_remote_retrieve_response_code($response)!==200 || filesize($tmp)>150*1024*1024) continue;
                self::commit_csv($tmp,$partner);
            } catch (Throwable $e) { /* No feed content, credential, or remote URL in public logs. */ }
            finally { @unlink($tmp); }
        }
    }

    // ---- 0.3.0 Stufe 1: Ticket-Angebot an BESTEHENDEN Events (Eventliste), nur hinter Freigabe + Client-Fähigkeit ----
    const CAPABILITY = 'partner-v1';
    const MAX_ITEMS = 6000;
    public static function provider_priority() {
        // Regel (Nutzer bestätigt 02.11.2026): pro Termin nur EIN Anbieter, feste Rangfolge, Reservix vor Eventim.
        $p = (defined('PFT_PROVIDER_PRIORITY') && is_array(PFT_PROVIDER_PRIORITY)) ? PFT_PROVIDER_PRIORITY : array('reservix','eventim');
        return array_values(array_filter($p, function($x){ return in_array($x, self::supported(), true); }));
    }
    public static function pick_offer($byPartner) {
        foreach (self::provider_priority() as $partner) if (isset($byPartner[$partner])) return $byPartner[$partner];
        return null;
    }
    public static function item_city($item) {
        foreach (array($item['city'] ?? '', $item['address']['city'] ?? '', $item['address']['addressLocality'] ?? '', $item['rpg_geo_label'] ?? '') as $c) {
            $c = self::city_map()[self::normalize($c)] ?? ''; if ($c !== '') return $c;
        }
        return '';
    }
    public static function item_timestamps($item) {
        $out = array();
        foreach (array($item['start'] ?? '', $item['_rpg_occurrence_start'] ?? '') as $v) { $t = is_string($v) && $v !== '' ? strtotime($v) : false; if ($t) $out[$t] = true; }
        foreach ((array)($item['rpg_event_series']['nearTerm'] ?? array()) as $o) { $t = is_array($o) && !empty($o['start']) ? strtotime((string)$o['start']) : false; if ($t) $out[$t] = true; }
        foreach (array('laterSeries') as $k) { $o = $item['rpg_event_series'][$k] ?? null; $t = is_array($o) && !empty($o['start']) ? strtotime((string)$o['start']) : false; if ($t) $out[$t] = true; }
        return array_keys($out);
    }
    public static function offer_for_item($item, $partnerRowsByDay, $now) {
        $title = (string)($item['title'] ?? ''); if ($title === '') return null;
        $city = self::item_city($item); if ($city === '') return null;
        $venue = (string)($item['venueName'] ?? '');
        $byPartner = array();
        foreach (self::item_timestamps($item) as $ts) {
            $day = (int)floor($ts / 86400);
            foreach (array($day-1,$day,$day+1) as $d) foreach (($partnerRowsByDay[$city][$d] ?? array()) as $r) {
                if ((int)$r['start_ts'] <= $now) continue;
                if (self::genre_denied($r['genre'] ?? '')) continue;
                if (!self::same_occurrence($r['title'], $r['venue'], $r['start_ts'], $title, $venue, $ts, $city)) continue;
                $cur = $byPartner[$r['partner']] ?? null;
                if (!$cur || (int)$r['start_ts'] < (int)$cur['_ts']) $byPartner[$r['partner']] = array('_ts'=>(int)$r['start_ts'],'partner'=>$r['partner'],'url'=>$r['affiliate_url'],'startUtc'=>gmdate('Y-m-d\TH:i:s+00:00',(int)$r['start_ts']));
            }
        }
        $pick = self::pick_offer($byPartner); if (!$pick) return null;
        return array('provider'=>$pick['partner'],'url'=>$pick['url'],'label'=>'Tickets bei '.ucfirst($pick['partner']),'adLabel'=>'Anzeige','rel'=>'sponsored noopener noreferrer','nextStart'=>$pick['startUtc']);
    }
    public static function enrich_items($items, $partnerRowsByDay, $now) {
        $n = 0;
        foreach ($items as $i => $item) {
            if (!is_array($item)) continue;
            $offer = self::offer_for_item($item, $partnerRowsByDay, $now);
            if ($offer) { $items[$i]['rpg_partner_offers'] = array($offer); $n++; }
        }
        return array($items, $n);
    }

    // ---- 0.4.0 Stufe 2: Reservix-Termine ohne eigenen Eintrag als Event-Items (gleiche Liste, gleiche Logik) ----
    const ITEMS_CAPABILITY = 'partner-items-v1';
    const MAX_PARTNER_SERIES = 1500;
    const MAX_PARTNER_DATES = 20;
    public static function haversine_km($la1, $lo1, $la2, $lo2) {
        $p = M_PI / 180; $a = sin(($la2-$la1)*$p/2)**2 + cos($la1*$p)*cos($la2*$p)*sin(($lo2-$lo1)*$p/2)**2;
        return 12742 * asin(min(1, sqrt($a)));
    }
    public static function iso_utc($ts) { return gmdate('Y-m-d\TH:i:s+00:00', (int)$ts); }
    public static function after_tomorrow_ts($now) {
        // Wie die Seite: "nahe Termine" = heute und morgen (Europa/Berlin); alles danach gehört zur Serie.
        $d = new DateTime('@' . (int)$now); $d->setTimezone(new DateTimeZone('Europe/Berlin')); $d->setTime(0,0,0); $d->modify('+2 day');
        return $d->getTimestamp();
    }
    public static function partner_item($key, $rows, $now) {
        $first = $rows[0]; $n = count($rows); $last = $rows[$n-1]; $after = self::after_tomorrow_ts($now);
        $near = array(); $later = null;
        foreach ($rows as $r) {
            if ((int)$r['start_ts'] < $after) { if (count($near) < 6) $near[] = array('start'=>self::iso_utc($r['start_ts']),'end'=>'','eventId'=>0,'canonicalUrl'=>''); }
            elseif ($later === null) $later = array('start'=>self::iso_utc($r['start_ts']),'end'=>'','eventId'=>0,'canonicalUrl'=>'');
        }
        $dates = array();
        foreach (array_slice($rows, 0, self::MAX_PARTNER_DATES) as $r) $dates[] = array('start'=>self::iso_utc($r['start_ts']),'url'=>$r['affiliate_url']);
        $name = ucfirst((string)$first['partner']);
        $id = 'partner:' . $first['partner'] . ':' . substr((string)$key, 0, 20);
        $item = array(
            'id'=>$id, 'title'=>(string)$first['title'], 'venueName'=>(string)$first['venue'], 'city'=>(string)$first['city'],
            'address'=>array('zip'=>(string)$first['postcode'],'city'=>(string)$first['city']),
            'lat'=>(float)$first['lat'], 'lng'=>(float)$first['lng'],
            'start'=>self::iso_utc($first['start_ts']), 'allDay'=>false,
            'eventType'=>(string)($first['genre'] ?? ''),
            'source_name'=>$name, 'source_attribution'=>'Anzeige – Ticketangebot von ' . $name,
            'rpg_event_series'=>array('count'=>$n,'first'=>self::iso_utc($first['start_ts']),'last'=>self::iso_utc($last['start_ts']),'identity'=>$id,'nearTerm'=>$near,'laterSeries'=>$later),
            'rpg_partner_offers'=>array(array('provider'=>$first['partner'],'url'=>$first['affiliate_url'],'label'=>'Tickets bei '.$name,'adLabel'=>'Anzeige','rel'=>'sponsored noopener noreferrer','nextStart'=>self::iso_utc($first['start_ts']))),
            'rpg_partner_item'=>array('v'=>1,'provider'=>$first['partner'],'total'=>$n,'dates'=>$dates,'moreLabel'=>'Weitere Termine bei '.$name.' ansehen und buchen','moreUrl'=>$first['affiliate_url'],'adLabel'=>'Anzeige'),
        );
        $img = trim((string)($first['image_url'] ?? ''));
        if ($img !== '') $item['rpg_event_media'] = array('verified'=>true,'imageUrl'=>$img,'source'=>$first['partner'].'-partner','credit'=>$name);
        return $item;
    }
    public static function build_partner_items($rows, $existingByDay, $now) {
        $byId = array();
        foreach ($rows as $r) {
            if ((int)$r['start_ts'] <= $now || self::genre_denied($r['genre'] ?? '')) continue;
            $byId[(string)$r['identity_key']][(string)$r['partner']][] = $r;
        }
        $out = array();
        foreach ($byId as $key => $byPartner) {
            // Ein Anbieter je Event, feste Rangfolge (Reservix vor Eventim).
            $pick = null; foreach (self::provider_priority() as $p) if (isset($byPartner[$p])) { $pick = $byPartner[$p]; break; }
            if (!$pick) continue;
            // Gibt es zu irgendeinem Termin dieser Serie schon einen eigenen Eintrag, bleibt es beim zusätzlichen Button am eigenen Event.
            $covered = false;
            foreach ($byPartner as $list) foreach ($list as $r) if (self::has_existing_match($r, $existingByDay)) { $covered = true; break 2; }
            if ($covered) continue;
            usort($pick, function($a,$b){ return (int)$a['start_ts'] <=> (int)$b['start_ts']; });
            $out[] = self::partner_item($key, $pick, $now);
        }
        usort($out, function($a,$b){ return strcmp($a['start'],$b['start']); });
        return array_slice($out, 0, self::MAX_PARTNER_SERIES);
    }
    public static function existing_by_day($items) {
        $by = array();
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            foreach (self::item_timestamps($item) as $ts) $by[(int)floor($ts/86400)][] = array('title'=>(string)($item['title'] ?? ''),'venue'=>(string)($item['venueName'] ?? ''),'ts'=>$ts);
        }
        return $by;
    }
    public function filter_events_response($result, $server, $request) {
        try {
            if (!($request instanceof WP_REST_Request) || $request->get_method() !== 'POST' || $request->get_route() !== '/ruhrpottguru/v1/events') return $result;
            if (!self::consumer_ready()) return $result;
            $params = $request->get_json_params();
            $caps = is_array($params) && isset($params['clientCaps']) && is_array($params['clientCaps']) ? $params['clientCaps'] : array();
            if (!in_array(self::CAPABILITY, $caps, true)) return $result; // alte Clients: Antwort unverändert
            if (!($result instanceof WP_REST_Response) || (int)$result->get_status() !== 200) return $result;
            $data = $result->get_data();
            if (!is_array($data) || empty($data['ok']) || !isset($data['items']) || !is_array($data['items']) || count($data['items']) > self::MAX_ITEMS) return $result;
            global $wpdb; $table = self::table(); $now = time(); $n = 0; $added = 0;
            $cities = array(); foreach ($data['items'] as $item) { if (is_array($item)) { $c = self::item_city($item); if ($c !== '') $cities[$c] = true; } }
            $cities = array_slice(array_keys($cities), 0, 12);
            $items = $data['items'];
            if ($cities) {
                $byDay = array();
                foreach ($cities as $city) {
                    $rows = (array)$wpdb->get_results($wpdb->prepare("SELECT partner,title,venue,start_ts,affiliate_url,genre FROM $table WHERE city=%s AND start_ts>%d ORDER BY start_ts ASC LIMIT 1500", $city, $now), ARRAY_A);
                    foreach ($rows as $r) $byDay[$city][(int)floor((int)$r['start_ts'] / 86400)][] = $r;
                }
                list($items, $n) = self::enrich_items($items, $byDay, $now);
            }
            // Stufe 2: Reservix-Termine ohne eigenen Eintrag. Nur mit zweiter Fähigkeit, nur Hauptliste (ohne untilDays/Specials), nur im Umkreis.
            if (in_array(self::ITEMS_CAPABILITY, $caps, true) && empty($params['untilDays'])) {
                $lat = isset($params['lat']) ? (float)$params['lat'] : 0.0; $lng = isset($params['lng']) ? (float)$params['lng'] : 0.0;
                $rad = isset($params['maxRadiusKm']) ? (float)$params['maxRadiusKm'] : 50.0; $rad = max(1.0, min(50.0, $rad));
                if ($lat > 40 && $lat < 60 && $lng > 0 && $lng < 20) {
                    $dLat = $rad / 111.0; $dLng = $rad / (111.0 * max(0.2, cos($lat * M_PI / 180)));
                    $prows = (array)$wpdb->get_results($wpdb->prepare("SELECT identity_key,partner,title,venue,city,postcode,lat,lng,start_ts,affiliate_url,image_url,genre FROM $table WHERE start_ts>%d AND lat BETWEEN %f AND %f AND lng BETWEEN %f AND %f ORDER BY start_ts ASC LIMIT 6000", $now, $lat-$dLat, $lat+$dLat, $lng-$dLng, $lng+$dLng), ARRAY_A);
                    $prows = array_values(array_filter($prows, function($r) use ($lat,$lng,$rad){ return $r['lat'] !== null && $r['lng'] !== null && self::haversine_km($lat,$lng,(float)$r['lat'],(float)$r['lng']) <= $rad; }));
                    $newItems = self::build_partner_items($prows, self::existing_by_day($data['items']), $now);
                    if ($newItems && count($items) + count($newItems) <= self::MAX_ITEMS + self::MAX_PARTNER_SERIES) { $items = array_merge($items, $newItems); $added = count($newItems); }
                }
            }
            $data['items'] = $items;
            if (!isset($data['meta']) || !is_array($data['meta'])) $data['meta'] = array();
            $data['meta']['partners'] = array('v' => self::VERSION, 'offers' => $n, 'items' => $added);
            $result->set_data($data);
            if (!headers_sent()) header('Cache-Control: no-store, private, max-age=0');
        } catch (Throwable $e) { /* Fail open: bei jedem Fehler die ursprüngliche Antwort ausliefern. */ }
        return $result;
    }
    public function register_rest() {
        register_rest_route('ruhrpottguru/v1','/ticket-partners/catalog',array('methods'=>'GET','callback'=>array($this,'rest_catalog'),'permission_callback'=>'__return_true',
          'args'=>array('city'=>array('sanitize_callback'=>'sanitize_text_field'),'limit'=>array('sanitize_callback'=>'absint'))));
        register_rest_route('ruhrpottguru/v1','/ticket-partners/event/(?P<id>\d+)',array('methods'=>'GET','callback'=>array($this,'rest_existing_event'),'permission_callback'=>'__return_true'));
    }
    public static function consumer_ready() {
        $good=get_option('pft_ticketpartners_last_good',array());
        return self::public_approved() && self::storage_approved('reservix') && isset($good['reservix']['at']) && (int)$good['reservix']['at']>time()-36*3600;
    }
    private function no_store_headers() { if(!headers_sent()){header('Cache-Control: no-store, private, max-age=0');header('X-Robots-Tag: noindex, nofollow');} }
    public function rest_catalog($request) {
        $this->no_store_headers();
        if (!self::consumer_ready()) return rest_ensure_response(array('ok'=>true,'enabled'=>false,'items'=>array()));
        $city=self::city_map()[self::normalize($request->get_param('city'))]??'';
        if (!$city) return rest_ensure_response(array('ok'=>true,'enabled'=>true,'items'=>array()));
        $limit=max(1,min(100,(int)($request->get_param('limit')?:30)));
        global $wpdb;$table=self::table();
        $rows=(array)$wpdb->get_results($wpdb->prepare("SELECT identity_key,partner,title,venue,city,lat,lng,start_at,start_ts,affiliate_url,image_url,genre FROM $table WHERE city=%s AND start_ts>%d ORDER BY start_ts ASC LIMIT 1500",$city,time()),ARRAY_A);
        // Read-only overlap detection against the existing canonical event catalog (never writes to rg_events).
        // 0.2.0: tolerant matching (title variants, venue, +-3 h, UTC timestamps) instead of exact identity only.
        $core=$wpdb->prefix.'rg_events';
        $existing=(array)$wpdb->get_results($wpdb->prepare("SELECT id,title,venue_name,city,start_at FROM $core WHERE city=%s AND status='active' AND start_at>=%s ORDER BY start_at ASC LIMIT 20000",$city,gmdate('Y-m-d H:i:s',time()-2*DAY_IN_SECONDS)),ARRAY_A);
        $byDay=array();
        foreach($existing as $e){$ts=strtotime((string)$e['start_at'].' UTC'); if($ts)$byDay[(int)floor($ts/86400)][]=array('title'=>$e['title'],'venue'=>$e['venue_name'],'ts'=>$ts);}
        $items=array();foreach($rows as $r){$k=$r['identity_key'];
            if(self::genre_denied($r['genre']??''))continue;
            if(self::has_existing_match($r,$byDay))continue;
            if(!isset($items[$k]))$items[$k]=array('identityKey'=>$k,'title'=>$r['title'],'city'=>$r['city'],'venueName'=>$r['venue'],'lat'=>$r['lat'],'lng'=>$r['lng'],'nextStart'=>$r['start_at'], 'imageUrl'=>$r['image_url'],'adLabel'=>'Anzeige','offers'=>array());
            if(!isset($items[$k]['offers'][$r['partner']]))$items[$k]['offers'][$r['partner']]=array('partner'=>$r['partner'],'url'=>$r['affiliate_url'],'label'=>'Tickets bei '.ucfirst($r['partner']));
        }
        $result=array_slice(array_values(array_filter($items,'is_array')),0,$limit);
        foreach($result as &$r)$r['offers']=array_values($r['offers']);unset($r);
        return rest_ensure_response(array('ok'=>true,'enabled'=>true,'items'=>$result));
    }
    public function rest_existing_event($request) {
        $this->no_store_headers();
        if (!self::consumer_ready()) return rest_ensure_response(array('ok'=>true,'enabled'=>false,'offers'=>array()));
        global $wpdb; $id=absint($request['id']);$events=$wpdb->prefix.'rg_events'; $table=self::table();
        $event=$wpdb->get_row($wpdb->prepare("SELECT id,title,city,venue_name,start_at FROM $events WHERE id=%d AND status='active' LIMIT 1",$id),ARRAY_A);
        if (!$event) return rest_ensure_response(array('ok'=>true,'enabled'=>true,'offers'=>array()));
        $eventTs=strtotime((string)$event['start_at'].' UTC'); if(!$eventTs) return rest_ensure_response(array('ok'=>true,'enabled'=>true,'offers'=>array()));
        $rows=(array)$wpdb->get_results($wpdb->prepare("SELECT partner,title,venue,affiliate_url,start_ts,genre FROM $table WHERE city=%s AND start_ts BETWEEN %d AND %d ORDER BY start_ts ASC LIMIT 200",$event['city'],$eventTs-self::MATCH_WINDOW,$eventTs+self::MATCH_WINDOW),ARRAY_A);
        $offers=array();
        foreach($rows as $r){
            if(self::genre_denied($r['genre']??''))continue;
            if(!self::same_occurrence($r['title'],$r['venue'],$r['start_ts'],$event['title'],$event['venue_name'],$eventTs,(string)$event['city']))continue;
            if(!isset($offers[$r['partner']]))$offers[$r['partner']]=array('partner'=>$r['partner'],'url'=>$r['affiliate_url'],'label'=>'Tickets bei '.ucfirst($r['partner']),'adLabel'=>'Anzeige');
        }
        return rest_ensure_response(array('ok'=>true,'enabled'=>true,'offers'=>array_values($offers)));
    }
    public function admin_menu() { add_management_page('PottFuchs Ticketpartner','PottFuchs Ticketpartner','manage_options','pft-ticketpartners',array($this,'admin_page')); }
    public function admin_page() {
        if (!current_user_can('manage_options')) wp_die('Keine Berechtigung.');
        $good=get_option('pft_ticketpartners_last_good',array());
        echo '<div class="wrap"><h1>PottFuchs Ticketpartner – Backend '.esc_html(self::VERSION).'</h1>';
        echo '<p>Ohne schriftliche Freigabe keine Feedverarbeitung. Keine automatische Eventkarte, kein eigener Event-SEO-Pfad, keine Änderungen am bestehenden Events-Backend.</p>';
        foreach(self::supported() as $partner){echo '<p><strong>'.esc_html(ucfirst($partner)).':</strong> Speicherfreigabe '.(self::storage_approved($partner)?'konfiguriert':'nicht konfiguriert');
            $s=$good[$partner]??null;if($s)echo ' · letzter vollständiger Import: '.esc_html(wp_date('d.m.Y H:i',(int)$s['at'])).' · Datensätze: '.esc_html((string)$s['count']);echo '</p>';}
        echo '<p>Öffentliche Datenausgabe: '.(self::consumer_ready()?'freigegeben und aktuell':'deaktiviert').'. Die Anzeige im Frontend bleibt unabhängig hiervon bis zur separaten Integration unverändert.</p></div>';
    }
}

class PFT_Ticketpartners_CLI {
    /** Import a provider CSV only after written rights clearance and an explicit confirmation.
     * ## OPTIONS
     * <partner> : reservix
     * <file> : Absolute path to a downloaded CSV
     * [--dry-run] : Validate without writing
     * [--confirm] : Confirm the permitted write
     */
    public function import($args,$assoc) {
        $partner=(string)($args[0]??''); $file=(string)($args[1]??'');
        if (!in_array($partner,PFT_Ticketpartners::supported(),true)) WP_CLI::error('Unbekannter Anbieter.');
        if (!empty($assoc['dry-run'])) {
            $result=PFT_Ticketpartners::inspect_csv($file,$partner);
            WP_CLI::line(wp_json_encode($result['stats'],JSON_UNESCAPED_UNICODE));return;
        }
        if (empty($assoc['confirm'])) WP_CLI::error('Kein Schreibzugriff ohne --confirm und schriftliche Speicherfreigabe.');
        try {$stats=PFT_Ticketpartners::commit_csv($file,$partner);WP_CLI::success(wp_json_encode($stats,JSON_UNESCAPED_UNICODE));}
        catch(Throwable $e){WP_CLI::error($e->getMessage());}
    }
    public function status() {WP_CLI::line(wp_json_encode(array('reservixStorageApproved'=>PFT_Ticketpartners::storage_approved('reservix'),'publicReady'=>PFT_Ticketpartners::consumer_ready(),'lastGood'=>get_option('pft_ticketpartners_last_good',array())),JSON_UNESCAPED_UNICODE));}
}
register_activation_hook(__FILE__,array('PFT_Ticketpartners','install'));
register_deactivation_hook(__FILE__,function(){wp_clear_scheduled_hook(PFT_Ticketpartners::CRON_HOOK);});
PFT_Ticketpartners::instance();
